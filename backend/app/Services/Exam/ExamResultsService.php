<?php

namespace App\Services\Exam;

use App\Enums\SchoolLevel;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamComment;
use App\Models\ExamResult;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Turns raw marks into what people read: per-subject and overall grades,
 * totals, averages and class positions, for a whole class or one report card.
 *
 * Ranking is by total marks, with tied students sharing a position (1, 2, 2, 4).
 * A student is only ranked once they have at least one mark.
 */
class ExamResultsService
{
    public function __construct(private readonly GradingService $grading) {}

    /** Active subjects a class sits, alphabetical. */
    public function subjectsFor(SchoolClass $class): Collection
    {
        return $class->subjects()->active()->orderBy('subjects.name')->get();
    }

    /** Active enrollments (with their student) for a class, alphabetical. */
    public function enrolled(SchoolClass $class): Collection
    {
        return ExamScope::activeEnrollments()
            ->where('enrollments.school_class_id', $class->id)
            ->with('student')
            ->get()
            // An enrollment whose student was deleted is an orphan, not a pupil.
            ->filter(fn (Enrollment $enrollment) => $enrollment->student !== null)
            ->sortBy(fn (Enrollment $enrollment) => mb_strtolower(ExamScope::personName($enrollment->student)))
            ->values();
    }

    /**
     * Full results for one class in one exam.
     *
     * @return array{exam: array, class: array, subjects: array, rows: array, summary: array}
     */
    public function classResults(Exam $exam, SchoolClass $class): array
    {
        ExamScope::assertCovers($exam, $class);

        $grader = $this->grading->forExam($exam);
        $subjects = $this->subjectsFor($class);
        $enrolled = $this->enrolled($class);

        $stored = ExamResult::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $enrolled->pluck('student_id'))
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $group) => $group->keyBy('subject_id'));

        $rows = $enrolled
            ->map(fn (Enrollment $enrollment) => $this->buildRow(
                $exam, $enrollment, $subjects, $stored->get($enrollment->student_id, collect()), $grader
            ))
            ->keyBy('student_id')
            ->all();

        $subjectStats = $this->applySubjectPositions($rows, $subjects);
        $this->applyOverallPositions($rows);

        $comments = ExamComment::where('exam_id', $exam->id)->whereIn('student_id', array_keys($rows))->get()->keyBy('student_id');
        foreach ($rows as $studentId => &$row) {
            $row['teacher_comment'] = $comments->get($studentId)?->teacher_comment;
            $row['head_comment'] = $comments->get($studentId)?->head_comment;
        }
        unset($row);

        $ordered = collect($rows)
            ->sortBy([
                fn ($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX),
                fn ($a, $b) => strcasecmp($a['full_name'], $b['full_name']),
            ])
            ->values();

        return [
            'exam' => $this->examSummary($exam),
            'class' => ['id' => $class->id, 'name' => $class->name, 'label' => ExamScope::classLabel($class)],
            'subjects' => $subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                ...$subjectStats[$subject->id],
            ])->values()->all(),
            'rows' => $ordered->all(),
            'summary' => $this->summarise($ordered, $grader),
        ];
    }

    /**
     * One student's report card.
     *
     * @throws ExamException when the student is not currently in a class
     */
    public function reportCard(Exam $exam, Student $student): array
    {
        $enrollment = ExamScope::activeEnrollments()
            ->where('enrollments.student_id', $student->id)
            ->with('schoolClass')
            ->first();

        if (! $enrollment?->schoolClass) {
            throw ExamException::invalid(
                __('exams.err.not_enrolled'),
                'STUDENT_NOT_ENROLLED',
            );
        }

        $results = $this->classResults($exam, $enrollment->schoolClass);

        return $this->cardFrom($exam, $results, collect($results['rows'])->firstWhere('student_id', $student->id));
    }

    /**
     * Every student's report card for a class, alphabetical, from a single
     * computation of the class results.
     *
     * @return array<int, array>
     */
    public function classReportCards(Exam $exam, SchoolClass $class): array
    {
        $results = $this->classResults($exam, $class);

        return collect($results['rows'])
            ->sortBy(fn (array $row) => mb_strtolower($row['full_name']))
            ->map(fn (array $row) => $this->cardFrom($exam, $results, $row))
            ->values()
            ->all();
    }

    /**
     * How much of the exam still lacks marks, per class. A cell is "entered" once
     * it has a mark or is explicitly marked absent.
     *
     * @return array{expected: int, entered: int, missing: int, by_class: array}
     */
    public function completeness(Exam $exam): array
    {
        $expected = $entered = 0;
        $byClass = [];

        foreach (ExamScope::classesFor($exam)->orderBy('school_classes.name')->get() as $class) {
            $subjectIds = $this->subjectsFor($class)->pluck('id');
            $studentIds = $this->enrolled($class)->pluck('student_id');

            if ($subjectIds->isEmpty() || $studentIds->isEmpty()) {
                continue;
            }

            $classExpected = $subjectIds->count() * $studentIds->count();
            $classEntered = ExamResult::query()
                ->where('exam_id', $exam->id)
                ->whereIn('student_id', $studentIds)
                ->whereIn('subject_id', $subjectIds)
                ->where(fn (Builder $q) => $q->whereNotNull('marks')->orWhere('is_absent', true))
                ->count();

            $expected += $classExpected;
            $entered += $classEntered;

            if ($classEntered < $classExpected) {
                $byClass[] = [
                    'class_id' => $class->id,
                    'class' => ExamScope::classLabel($class),
                    'missing' => $classExpected - $classEntered,
                ];
            }
        }

        return [
            'expected' => $expected,
            'entered' => $entered,
            'missing' => $expected - $entered,
            'by_class' => $byClass,
        ];
    }

    public function examSummary(Exam $exam): array
    {
        $exam->loadMissing(['term', 'academicYear']);

        return [
            'id' => $exam->id,
            'name' => $exam->name,
            'type' => $exam->type,
            'status' => $exam->status,
            'term' => $exam->term?->name,
            'academic_year' => $exam->academicYear?->name ?? $exam->term?->academicYear?->name,
            'start_date' => $exam->start_date?->toDateString(),
            'end_date' => $exam->end_date?->toDateString(),
            'out_of' => (int) ($exam->out_of ?? 100),
            'marks_deadline' => $exam->marks_deadline?->toDateString(),
            'next_term_opens' => $exam->next_term_opens?->toDateString(),
            'hold_for_fees' => (bool) $exam->hold_for_fees,
        ];
    }

    /** Marks as a percentage of the exam's total, which is what grades, averages and positions use. */
    public function percent(Exam $exam, ?float $marks): ?float
    {
        if ($marks === null) {
            return null;
        }
        $outOf = max(1, (int) ($exam->out_of ?? 100));

        return $outOf === 100 ? $marks : round($marks / $outOf * 100, 2);
    }

    /** A grade remark in the language of the reader (the scale stores the English wording). */
    public function remarkLabel(?string $remark): ?string
    {
        if ($remark === null) {
            return null;
        }
        $key = 'exams.remarks.'.Str::snake(Str::lower($remark));

        return Lang::has($key) ? __($key) : $remark;
    }

    /**
     * Secondary-school division from the best seven subjects (NECTA style): 7-17 I, 18-21 II, 22-25 III,
     * 26-33 IV, 34-35 is 0. Primary schools have none, and a pupil with fewer than seven subjects has none.
     *
     * @param  array<int, int>  $points  grade points of every subject the pupil sat
     * @return array{code: string, label: string, points: int}|null
     */
    public function division(array $points): ?array
    {
        if (! in_array(ExamScope::school()?->level, [SchoolLevel::Secondary, SchoolLevel::Sekondari], true) || count($points) < 7) {
            return null;
        }
        sort($points);
        $sum = array_sum(array_slice($points, 0, 7));
        $code = match (true) {
            $sum <= 17 => 'I',
            $sum <= 21 => 'II',
            $sum <= 25 => 'III',
            $sum <= 33 => 'IV',
            default => '0',
        };

        return ['code' => $code, 'label' => __('exams.division.'.$code), 'points' => $sum];
    }

    /** Days present, absent and late in the exam's term, for the report card. */
    private function attendanceFor(Exam $exam, int $studentId): ?array
    {
        $exam->loadMissing('term');
        $term = $exam->term;
        if (! $term?->start_date || ! $term?->end_date) {
            return null;
        }

        $counts = Attendance::query()
            ->where('student_id', $studentId)
            ->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()])
            ->selectRaw('status, count(*) as days')->groupBy('status')->pluck('days', 'status');

        $total = (int) $counts->sum();
        if ($total === 0) {
            return null;
        }
        $present = (int) ($counts['present'] ?? 0) + (int) ($counts['late'] ?? 0);

        return [
            'days' => $total,
            'present' => (int) ($counts['present'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'percent' => round($present / $total * 100),
        ];
    }

    /** Fees still owed for the exam's academic year, and whether the school holds results back for them. */
    public function feesFor(Exam $exam, int $studentId): array
    {
        if (! $exam->hold_for_fees) {
            return ['hold' => false, 'outstanding_cents' => 0];
        }

        $due = Invoice::allSchools()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $exam->academic_year_id)
            ->get()
            ->sum(fn ($invoice) => $invoice->balanceDueCents());

        return ['hold' => $due > 0, 'outstanding_cents' => (int) $due];
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function cardFrom(Exam $exam, array $results, array $row): array
    {
        $subjects = collect($results['subjects'])->map(fn (array $subject) => [
            'subject_id' => $subject['id'],
            'name' => $subject['name'],
            'code' => $subject['code'],
            'class_average' => $subject['average'],
            ...$row['cells'][$subject['id']],
        ])->values()->all();

        $studentId = $row['student_id'];

        return [
            'exam' => $results['exam'],
            'student' => [
                'id' => $row['student_id'],
                'full_name' => $row['full_name'],
                'admission_number' => $row['admission_number'],
                'class' => $results['class']['label'],
            ],
            'subjects' => $subjects,
            'summary' => [
                'total' => $row['total'],
                'average' => $row['average'],
                'grade' => $row['grade'],
                'remark' => $row['remark'],
                'position' => $row['position'],
                'class_size' => $results['summary']['class_size'],
                'ranked' => $results['summary']['ranked'],
                'subjects_count' => $row['subjects_count'],
                'total_points' => $row['total_points'],
                'missing' => $row['missing'],
                'division' => $row['division'],
            ],
            'comments' => ['teacher' => $row['teacher_comment'] ?? null, 'head' => $row['head_comment'] ?? null],
            'attendance' => $this->attendanceFor($exam, $studentId),
            'fees' => $this->feesFor($exam, $studentId),
            'legend' => collect($this->grading->forExam($exam)->ranges())
                ->map(fn (array $band) => [...$band, 'remark' => $this->remarkLabel($band['remark'])])->all(),
        ];
    }

    private function buildRow(Exam $exam, Enrollment $enrollment, Collection $subjects, Collection $results, GradingService $grader): array
    {
        $cells = [];
        $total = 0.0;
        $sat = $missing = $points = 0;
        $pointsKnown = true;
        $pointList = [];

        foreach ($subjects as $subject) {
            /** @var ExamResult|null $result */
            $result = $results->get($subject->id);
            $raw = $result?->marks;
            $marks = $this->percent($exam, $raw);
            $absent = (bool) $result?->is_absent;
            $band = $grader->gradeFor($marks);

            if ($marks !== null) {
                $total += $marks;
                $sat++;
                if ($band?->points === null) {
                    $pointsKnown = false;
                } else {
                    $points += $band->points;
                    $pointList[] = $band->points;
                }
            } elseif (! $absent) {
                $missing++;
            }

            $cells[$subject->id] = [
                'marks' => $marks,
                'raw' => $raw,
                'absent' => $absent,
                'grade' => $band?->grade,
                'remark' => $this->remarkLabel($band?->remark),
                'position' => null,
            ];
        }

        $average = $sat > 0 ? round($total / $sat, 2) : null;
        $overall = $grader->gradeFor($average);

        return [
            'student_id' => $enrollment->student_id,
            'full_name' => ExamScope::personName($enrollment->student),
            'admission_number' => $enrollment->admission_number,
            'cells' => $cells,
            'total' => $sat > 0 ? round($total, 2) : null,
            'average' => $average,
            'grade' => $overall?->grade,
            'remark' => $this->remarkLabel($overall?->remark),
            'subjects_count' => $sat,
            'total_points' => $sat > 0 && $pointsKnown ? $points : null,
            'division' => $pointsKnown ? $this->division($pointList) : null,
            'missing' => $missing,
            'position' => null,
        ];
    }

    /**
     * Fill in each student's position within every subject and return the
     * per-subject class statistics.
     *
     * @return array<int, array{sat: int, absent: int, average: float|null, highest: float|null, lowest: float|null}>
     */
    private function applySubjectPositions(array &$rows, Collection $subjects): array
    {
        $stats = [];

        foreach ($subjects as $subject) {
            $marks = [];
            $absent = 0;

            foreach ($rows as $studentId => $row) {
                $cell = $row['cells'][$subject->id];
                if ($cell['marks'] !== null) {
                    $marks[$studentId] = $cell['marks'];
                } elseif ($cell['absent']) {
                    $absent++;
                }
            }

            foreach ($this->rank($marks) as $studentId => $position) {
                $rows[$studentId]['cells'][$subject->id]['position'] = $position;
            }

            $stats[$subject->id] = [
                'sat' => count($marks),
                'absent' => $absent,
                'average' => $marks ? round(array_sum($marks) / count($marks), 2) : null,
                'highest' => $marks ? max($marks) : null,
                'lowest' => $marks ? min($marks) : null,
            ];
        }

        return $stats;
    }

    private function applyOverallPositions(array &$rows): void
    {
        $totals = [];
        foreach ($rows as $studentId => $row) {
            if ($row['total'] !== null) {
                $totals[$studentId] = $row['total'];
            }
        }

        foreach ($this->rank($totals) as $studentId => $position) {
            $rows[$studentId]['position'] = $position;
        }
    }

    /**
     * Competition ranking, highest first: equal scores share a position and the
     * next position skips accordingly (1, 2, 2, 4).
     *
     * @param  array<int, float>  $scores  keyed by student id
     * @return array<int, int>
     */
    private function rank(array $scores): array
    {
        arsort($scores);

        $positions = [];
        $previous = null;
        $position = 0;
        $seen = 0;

        foreach ($scores as $studentId => $score) {
            $seen++;
            if ($previous === null || round($score, 2) !== round($previous, 2)) {
                $position = $seen;
                $previous = $score;
            }
            $positions[$studentId] = $position;
        }

        return $positions;
    }

    private function summarise(Collection $rows, GradingService $grader): array
    {
        $ranked = $rows->filter(fn (array $row) => $row['position'] !== null);
        $averages = $ranked->pluck('average');

        return [
            'class_size' => $rows->count(),
            'ranked' => $ranked->count(),
            'class_average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'top_total' => $ranked->max('total'),
            'missing_cells' => $rows->sum('missing'),
            'complete' => $rows->isNotEmpty() && $rows->sum('missing') === 0,
            'grade_distribution' => $grader->bands()
                ->mapWithKeys(fn ($band) => [$band->grade => $ranked->where('grade', $band->grade)->count()])
                ->all(),
        ];
    }
}
