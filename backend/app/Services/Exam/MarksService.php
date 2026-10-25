<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamMarkChange;
use App\Models\ExamResult;
use App\Models\ExamSubmission;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reading and writing the marks-entry grid for one class and one subject.
 *
 * Marks are entered out of the exam's total (100 unless the exam says otherwise).
 * Classroom teachers may only enter their own class and subject, only until the
 * marks deadline, and not after they have submitted; coordinators and heads can
 * always correct. Every change to a stored mark is written to an audit trail.
 */
class MarksService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly ExamResultsService $results,
        private readonly TeacherAssignmentService $assignments,
    ) {}

    public function grid(Exam $exam, SchoolClass $class, Subject $subject, ?User $actor = null): array
    {
        ExamScope::assertCovers($exam, $class);
        $this->assertSubjectTaught($class, $subject);
        if ($actor) {
            $this->assignments->assertMayEnter($actor, $class, $subject);
        }

        $grader = $this->grading->forExam($exam);
        $enrolled = $this->results->enrolled($class);

        $stored = ExamResult::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereIn('student_id', $enrolled->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        $students = $enrolled->map(function ($enrollment) use ($stored, $grader, $exam) {
            $result = $stored->get($enrollment->student_id);

            return [
                'student_id' => $enrollment->student_id,
                'full_name' => ExamScope::personName($enrollment->student),
                'admission_number' => $enrollment->admission_number,
                'marks' => $result?->marks,
                'absent' => (bool) $result?->is_absent,
                'grade' => $grader->gradeFor($this->results->percent($exam, $result?->marks))?->grade,
                'remarks' => $result?->remarks,
            ];
        })->values();

        $submission = $this->submission($exam, $class, $subject);
        $locked = $actor && $this->assignments->isRestricted($actor) && $this->lockReason($exam, $submission) !== null;

        return [
            'exam' => $this->results->examSummary($exam),
            'class' => ['id' => $class->id, 'label' => ExamScope::classLabel($class)],
            'subject' => ['id' => $subject->id, 'name' => $subject->name, 'code' => $subject->code],
            'editable' => $exam->isOpen() && ! $locked,
            'out_of' => (int) $exam->out_of,
            'submitted' => $submission !== null,
            'submitted_at' => $submission?->submitted_at?->toIso8601String(),
            'lock_reason' => $locked ? $this->lockReason($exam, $submission) : null,
            'students' => $students->all(),
            'progress' => [
                'total' => $students->count(),
                'entered' => $students->filter(fn ($s) => $s['marks'] !== null || $s['absent'])->count(),
            ],
        ];
    }

    /**
     * Save a batch of marks. Every entry is validated against the class roster
     * before anything is written, and the write is all-or-nothing.
     *
     * An entry with no mark, not absent and no remark clears any stored result,
     * which is how a teacher blanks a cell that was entered by mistake.
     *
     * @param  array<int, array{student_id: int, marks?: float|int|string|null, absent?: bool, remarks?: string|null}>  $entries
     * @return array{saved: int, cleared: int}
     */
    public function save(Exam $exam, SchoolClass $class, Subject $subject, array $entries, ?User $actor): array
    {
        $userId = $actor?->id;
        if (! $exam->isOpen()) {
            throw $exam->isPublished()
                ? ExamException::state(__('exams.state.results_published'), 'EXAM_PUBLISHED')
                : ExamException::state(__('exams.state.not_open'), 'EXAM_NOT_OPEN');
        }

        ExamScope::assertCovers($exam, $class);
        $this->assertSubjectTaught($class, $subject);
        if ($actor) {
            $this->assignments->assertMayEnter($actor, $class, $subject);
            if ($this->assignments->isRestricted($actor)) {
                $this->assertNotLocked($exam, $this->submission($exam, $class, $subject));
            }
        }

        $studentIds = collect($entries)->pluck('student_id')->map(fn ($id) => (int) $id);

        if ($studentIds->count() !== $studentIds->unique()->count()) {
            throw ExamException::invalid(__('exams.err.duplicate_student'), 'DUPLICATE_STUDENT', [
                'student_ids' => $studentIds->duplicates()->unique()->values()->all(),
            ]);
        }

        $unknown = $studentIds->diff($this->results->enrolled($class)->pluck('student_id'));
        if ($unknown->isNotEmpty()) {
            throw ExamException::invalid(
                __('exams.err.student_not_in_class', ['class' => ExamScope::classLabel($class)]),
                'STUDENT_NOT_IN_CLASS',
                ['student_ids' => $unknown->values()->all()],
            );
        }

        $max = (int) $exam->out_of;
        foreach (array_values($entries) as $index => $entry) {
            $raw = $entry['marks'] ?? null;
            if (! ($entry['absent'] ?? false) && $raw !== null && $raw !== '' && (float) $raw > $max) {
                throw ExamException::invalid(
                    __('exams.err.marks_over', ['position' => $index + 1, 'max' => $max]),
                    'MARKS_OVER_MAX',
                    ['row' => $index + 1],
                );
            }
        }

        $grader = $this->grading->forExam($exam);
        $saved = $cleared = 0;
        $actorName = $actor?->name;

        DB::transaction(function () use ($entries, $exam, $subject, $userId, $grader, $actorName, &$saved, &$cleared) {
            foreach ($entries as $entry) {
                $absent = (bool) ($entry['absent'] ?? false);
                $marks = $absent ? null : ($entry['marks'] ?? null);
                $marks = $marks === null || $marks === '' ? null : round((float) $marks, 2);
                $remarks = filled($entry['remarks'] ?? null) ? trim($entry['remarks']) : null;

                $key = ['exam_id' => $exam->id, 'student_id' => (int) $entry['student_id'], 'subject_id' => $subject->id];
                $existing = ExamResult::where($key)->first();

                if ($marks === null && ! $absent && $remarks === null) {
                    if ($existing) {
                        $this->record($key, $existing, null, false, $userId, $actorName);
                        $cleared += $existing->delete() ? 1 : 0;
                    }

                    continue;
                }

                $this->record($key, $existing, $marks, $absent, $userId, $actorName);

                ExamResult::updateOrCreate($key, [
                    'marks' => $marks,
                    'is_absent' => $absent,
                    'grade' => $grader->gradeFor($this->results->percent($exam, $marks))?->grade,
                    'remarks' => $remarks,
                    'entered_by' => $userId,
                ]);
                $saved++;
            }
        });

        return ['saved' => $saved, 'cleared' => $cleared];
    }

    /** The teacher hands the marks in: they are complete and now locked for classroom teachers. */
    public function submit(Exam $exam, SchoolClass $class, Subject $subject, ?User $actor): ExamSubmission
    {
        if (! $exam->isOpen()) {
            throw ExamException::state(__('exams.state.not_open'), 'EXAM_NOT_OPEN');
        }
        ExamScope::assertCovers($exam, $class);
        $this->assertSubjectTaught($class, $subject);
        if ($actor) {
            $this->assignments->assertMayEnter($actor, $class, $subject);
        }

        $grid = $this->grid($exam, $class, $subject);
        if ($grid['progress']['entered'] < $grid['progress']['total'] || $grid['progress']['total'] === 0) {
            throw ExamException::invalid(__('exams.err.submit_incomplete'), 'SUBMIT_INCOMPLETE');
        }

        return ExamSubmission::updateOrCreate(
            ['exam_id' => $exam->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id],
            ['submitted_by' => $actor?->id, 'submitted_at' => now()],
        );
    }

    /** A coordinator or head opens submitted marks for changes again. */
    public function reopen(Exam $exam, SchoolClass $class, Subject $subject): void
    {
        $removed = ExamSubmission::where(['exam_id' => $exam->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id])->delete();
        if (! $removed) {
            throw ExamException::state(__('exams.err.not_submitted'), 'NOT_SUBMITTED');
        }
    }

    public function submission(Exam $exam, SchoolClass $class, Subject $subject): ?ExamSubmission
    {
        return ExamSubmission::where(['exam_id' => $exam->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id])->first();
    }

    private function lockReason(Exam $exam, ?ExamSubmission $submission): ?string
    {
        if ($submission) {
            return __('exams.err.submitted_locked');
        }
        if ($exam->deadlinePassed()) {
            return __('exams.err.deadline_passed', ['date' => $exam->marks_deadline->toDateString()]);
        }

        return null;
    }

    private function assertNotLocked(Exam $exam, ?ExamSubmission $submission): void
    {
        if ($reason = $this->lockReason($exam, $submission)) {
            throw ExamException::forbidden($reason, $submission ? 'MARKS_SUBMITTED' : 'MARKS_DEADLINE_PASSED');
        }
    }

    /** Write an audit row when the stored value actually changes. */
    private function record(array $key, ?ExamResult $existing, ?float $newMarks, bool $newAbsent, ?int $userId, ?string $userName): void
    {
        $oldMarks = $existing?->marks;
        $oldAbsent = (bool) $existing?->is_absent;

        if ($existing && $oldMarks === $newMarks && $oldAbsent === $newAbsent) {
            return;
        }
        if (! $existing && $newMarks === null && ! $newAbsent) {
            return;
        }

        ExamMarkChange::create($key + [
            'old_marks' => $oldMarks,
            'new_marks' => $newMarks,
            'old_absent' => $oldAbsent,
            'new_absent' => $newAbsent,
            'changed_by' => $userId,
            'changed_by_name' => $userName,
        ]);
    }

    private function assertSubjectTaught(SchoolClass $class, Subject $subject): void
    {
        $taught = $class->subjects()->active()->whereKey($subject->id)->exists();

        if (! $taught) {
            throw ExamException::invalid(
                __('exams.err.subject_not_in_class', ['subject' => $subject->name, 'class' => ExamScope::classLabel($class)]),
                'SUBJECT_NOT_IN_CLASS',
            );
        }
    }
}
