<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\SchoolNotification;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The exam lifecycle: draft -> open (teachers enter marks) -> published
 * (heads approve; parents can see results). Publishing is reversible so a
 * mistake can be corrected, at the cost of hiding results from parents again;
 * an exam can only be unpublished with a reason, which goes into the audit log.
 */
class ExamWorkflowService
{
    /** How many parents the last publish() texted. */
    public int $smsSent = 0;

    public function __construct(
        private readonly GradingService $grading,
        private readonly ExamResultsService $results,
        private readonly ExamCommentService $comments,
    ) {}

    public function open(Exam $exam): Exam
    {
        if (! $exam->isDraft()) {
            throw ExamException::state($exam->isPublished()
                ? __('exams.state.already_published')
                : __('exams.state.already_open'));
        }

        $covered = ExamScope::classesFor($exam)->get();
        if ($covered->every(fn ($class) => $this->results->subjectsFor($class)->isEmpty())) {
            throw ExamException::invalid(__('exams.err.no_subjects'), 'EXAM_NO_SUBJECTS');
        }

        // An exam with nobody to mark would open to empty sheets and then be impossible to publish.
        if ($covered->every(fn ($class) => $this->results->enrolled($class)->isEmpty())) {
            throw ExamException::invalid(__('exams.err.no_pupils'), 'EXAM_NO_PUPILS');
        }

        $this->grading->ensureDefaults();
        $exam->update(['status' => Exam::STATUS_OPEN]);

        return $exam->refresh();
    }

    public function publish(Exam $exam, ?int $userId, bool $smsParents = false): Exam
    {
        if ($exam->isPublished()) {
            throw ExamException::state(__('exams.state.already_published'));
        }
        if ($exam->isDraft()) {
            throw ExamException::state(__('exams.state.open_first'), 'EXAM_NOT_OPEN');
        }

        $completeness = $this->results->completeness($exam);

        if ($completeness['expected'] === 0) {
            throw ExamException::invalid(__('exams.err.no_classes'), 'EXAM_NO_CLASSES');
        }

        if ($completeness['entered'] === 0) {
            throw ExamException::invalid(__('exams.err.empty'), 'EXAM_EMPTY');
        }

        if ($completeness['missing'] > 0) {
            throw ExamException::invalid(
                __('exams.err.incomplete', ['count' => $completeness['missing']]),
                'EXAM_INCOMPLETE',
                $completeness,
            );
        }

        $this->comments->assertReadyToPublish($exam);

        DB::transaction(function () use ($exam, $userId) {
            $this->grading->bands();
            $snapshot = $this->grading->snapshot();

            // Stored grades were written against whatever scale was live at entry
            // time; bring them in line with the scale being frozen now.
            $exam->results()->whereNotNull('marks')->get()->each(function (ExamResult $result) use ($exam) {
                $grade = $this->grading->gradeFor($this->results->percent($exam, $result->marks))?->grade;
                if ($result->grade !== $grade) {
                    $result->update(['grade' => $grade]);
                }
            });

            $exam->update([
                'status' => Exam::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by' => $userId,
                'grading_snapshot' => $snapshot,
            ]);
        });

        $this->notifyStaff($exam, $userId);
        $this->smsSent = $smsParents ? $this->textParents($exam) : 0;

        return $exam->refresh();
    }

    public function unpublish(Exam $exam, string $reason, ?int $userId = null): Exam
    {
        if (! $exam->isPublished()) {
            throw ExamException::state(__('exams.state.only_published'));
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ExamException::invalid(__('exams.state.reason_needed'), 'REASON_REQUIRED');
        }

        $exam->update([
            'status' => Exam::STATUS_OPEN,
            'published_at' => null,
            'published_by' => null,
            'grading_snapshot' => null,
        ]);

        AuditLogger::log('exam_unpublished', $exam, [
            'exam' => $exam->name,
            'reason' => $reason,
            'unpublished_by' => $userId ? User::find($userId)?->name : null,
        ]);

        return $exam->refresh();
    }

    /**
     * Narrowing an exam must not silently orphan marks: a class that already has
     * marks stays in the exam until those marks are cleared.
     *
     * @param  array<int, int>  $classIds  the classes the exam will be limited to
     */
    public function assertClassesCanBeDropped(Exam $exam, bool $allClasses, array $classIds): void
    {
        if ($allClasses) {
            return;
        }

        $marked = ExamScope::classes()
            ->whereNotIn('school_classes.id', $classIds)
            ->get()
            ->filter(fn ($class) => ExamResult::query()
                ->where('exam_id', $exam->id)
                ->whereIn('student_id', $this->results->enrolled($class)->pluck('student_id'))
                ->exists());

        if ($marked->isNotEmpty()) {
            throw ExamException::invalid(
                __('exams.err.class_has_marks', ['classes' => $marked->map(fn ($c) => ExamScope::classLabel($c))->join(', ', ' & ')]),
                'CLASS_HAS_MARKS',
                ['class_ids' => $marked->pluck('id')->all()],
            );
        }
    }

    /** Structural edits (name, term, dates, deletion) are locked once published. */
    public function assertNotPublished(Exam $exam, string $action = 'edited'): void
    {
        if ($exam->isPublished()) {
            throw ExamException::state(
                __('exams.state.published_locked', ['action' => __('exams.state.action_'.$action)]),
                'EXAM_PUBLISHED',
            );
        }
    }

    /** Tell staff the results are out, in the language of the person who published. */
    private function notifyStaff(Exam $exam, ?int $userId): void
    {
        try {
            $exam->loadMissing('term');
            $publisher = $userId ? User::find($userId)?->name : null;
            $term = $exam->term ? " ({$exam->term->name})" : '';

            SchoolNotification::create([
                'school_id' => ExamScope::school()?->id,
                'type' => 'exam_published',
                'title' => __('exams.notify.published_title', ['exam' => $exam->name]),
                'body' => $publisher
                    ? __('exams.notify.published_by', ['exam' => $exam->name, 'term' => $term, 'user' => $publisher])
                    : __('exams.notify.published_plain', ['exam' => $exam->name, 'term' => $term]),
                'data' => ['exam_id' => $exam->id],
                'recipient_role' => 'all',
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // The results are already published; a failed notice must not undo that.
            Log::warning('[Exam] publish notification failed: '.$e->getMessage(), ['exam_id' => $exam->id]);
        }
    }

    /** One short SMS to the guardian of every pupil whose results were just published. */
    private function textParents(Exam $exam): int
    {
        $sent = 0;
        $school = ExamScope::school()?->name ?? 'ShulePay';
        $sms = app(SmsService::class);

        try {
            foreach (ExamScope::classesFor($exam)->get() as $class) {
                foreach ($this->results->enrolled($class) as $enrollment) {
                    if (! $exam->results()->where('student_id', $enrollment->student_id)->exists()) {
                        continue;
                    }
                    $sent += $sms->notifyGuardians($enrollment->student, __('exams.notify.sms', [
                        'school' => $school,
                        'exam' => $exam->name,
                        'student' => ExamScope::personName($enrollment->student),
                    ], 'sw'));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Exam] results SMS failed: '.$e->getMessage(), ['exam_id' => $exam->id]);
        }

        return $sent;
    }
}
