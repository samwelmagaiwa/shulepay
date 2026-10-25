<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Student;

/**
 * What the marks say: subject performance, pass rates, grade spread, best and weakest pupils, how a class
 * moved across exams, one pupil's history, and a weighted "combined" result over several exams.
 * Everything is derived from ExamResultsService so ranking and grading stay in one place.
 */
class ExamAnalysisService
{
    public function __construct(
        private readonly ExamResultsService $results,
        private readonly GradingService $grading,
    ) {}

    /** The lowest mark that still counts as a pass: the bottom of the second-lowest grade band. */
    public function passMark(?Exam $exam = null): float
    {
        $bands = ($exam ? $this->grading->forExam($exam) : $this->grading)->bands();

        return $bands->count() >= 2 ? (float) $bands->values()[$bands->count() - 2]->min_marks : 40.0;
    }

    public function analyse(Exam $exam, SchoolClass $class): array
    {
        $r = $this->results->classResults($exam, $class);
        $grader = $this->grading->forExam($exam);
        $pass = $this->passMark($exam);
        $bands = $grader->bands();
        $rows = collect($r['rows']);

        $subjects = collect($r['subjects'])->map(function (array $subject) use ($rows, $bands, $pass) {
            $cells = $rows->map(fn ($row) => $row['cells'][$subject['id']])->filter(fn ($c) => $c['marks'] !== null);
            $passed = $cells->filter(fn ($c) => $c['marks'] >= $pass)->count();
            $best = $rows->filter(fn ($row) => ($row['cells'][$subject['id']]['position'] ?? null) === 1)->pluck('full_name')->values();

            return [
                'id' => $subject['id'],
                'name' => $subject['name'],
                'sat' => $subject['sat'],
                'absent' => $subject['absent'],
                'average' => $subject['average'],
                'highest' => $subject['highest'],
                'lowest' => $subject['lowest'],
                'pass_rate' => $cells->isNotEmpty() ? round($passed / $cells->count() * 100) : null,
                'best' => $best->all(),
                'grades' => $bands->mapWithKeys(fn ($b) => [$b->grade => $cells->where('grade', $b->grade)->count()])->all(),
            ];
        })->values();

        $ranked = $rows->filter(fn ($row) => $row['position'] !== null)->values();
        $passedOverall = $ranked->filter(fn ($row) => $row['average'] >= $pass)->count();

        return [
            'exam' => $r['exam'],
            'class' => $r['class'],
            'pass_mark' => $pass,
            'subjects' => $subjects->all(),
            'overall' => [
                'ranked' => $ranked->count(),
                'class_average' => $r['summary']['class_average'],
                'pass_rate' => $ranked->isNotEmpty() ? round($passedOverall / $ranked->count() * 100) : null,
                'grades' => $r['summary']['grade_distribution'],
                'divisions' => $ranked->pluck('division.code')->filter()->countBy()->all(),
            ],
            'top' => $ranked->take(5)->map(fn ($row) => $this->brief($row))->values()->all(),
            // the five lowest, the weakest first
            'weakest' => $ranked->sortByDesc('position')->take(5)->map(fn ($row) => $this->brief($row))->values()->all(),
            'trend' => $this->trend($exam, $class),
        ];
    }

    /** The class average in this exam and the other exams of the same year, oldest first. */
    private function trend(Exam $exam, SchoolClass $class): array
    {
        $others = Exam::query()
            ->with('term')
            ->where('academic_year_id', $exam->academic_year_id)
            ->whereIn('status', [Exam::STATUS_OPEN, Exam::STATUS_PUBLISHED])
            ->orderBy('start_date')->orderBy('id')
            ->get()
            ->filter(fn (Exam $e) => $e->coversClass($class))
            ->take(8);

        return $others->map(function (Exam $e) use ($class) {
            $summary = $this->results->classResults($e, $class)['summary'];

            return ['exam_id' => $e->id, 'name' => $e->name, 'term' => $e->term?->name, 'average' => $summary['class_average'], 'ranked' => $summary['ranked']];
        })->filter(fn ($t) => $t['average'] !== null)->values()->all();
    }

    /** One pupil across the exams of their class: average, grade and position in each. */
    public function studentHistory(Student $student): array
    {
        $enrollment = ExamScope::activeEnrollments()->where('enrollments.student_id', $student->id)->with('schoolClass')->first();
        if (! $enrollment?->schoolClass) {
            throw ExamException::invalid(__('exams.err.not_enrolled'), 'STUDENT_NOT_ENROLLED');
        }
        $class = $enrollment->schoolClass;

        $exams = Exam::query()->with('term')
            ->whereIn('status', [Exam::STATUS_OPEN, Exam::STATUS_PUBLISHED])
            ->whereHas('results', fn ($q) => $q->where('student_id', $student->id))
            ->orderBy('start_date')->orderBy('id')->get()
            ->filter(fn (Exam $e) => $e->coversClass($class))->take(8);

        $history = $exams->map(function (Exam $e) use ($class, $student) {
            $r = $this->results->classResults($e, $class);
            $row = collect($r['rows'])->firstWhere('student_id', $student->id);

            return $row ? [
                'exam_id' => $e->id,
                'name' => $e->name,
                'term' => $e->term?->name,
                'average' => $row['average'],
                'grade' => $row['grade'],
                'position' => $row['position'],
                'class_size' => $r['summary']['class_size'],
                'division' => $row['division'],
            ] : null;
        })->filter()->values();

        return ['student' => ['id' => $student->id, 'name' => ExamScope::personName($student), 'class' => ExamScope::classLabel($class)], 'history' => $history->all()];
    }

    /**
     * A weighted result over several exams of one class (for example a mid-term at 40% and a terminal at 60%).
     * A subject missing from one exam is averaged over the exams that have it.
     *
     * @param  array<int, float>  $weights  exam id => weight, adding up to 100
     */
    public function combined(SchoolClass $class, array $weights): array
    {
        $ids = array_keys($weights);
        if (count($ids) < 2 || abs(array_sum($weights) - 100) > 0.01 || min($weights) <= 0) {
            throw ExamException::invalid(__('exams.err.combined_weights'), 'BAD_WEIGHTS');
        }

        $exams = Exam::with('term')->whereIn('id', $ids)->get()->keyBy('id');
        $perExam = [];
        foreach ($ids as $id) {
            $exam = $exams->get($id) ?? throw ExamException::invalid(__('exams.err.combined_weights'), 'BAD_WEIGHTS');
            ExamScope::assertCovers($exam, $class);
            $perExam[$id] = $this->results->classResults($exam, $class);
        }

        $subjects = collect($perExam)->flatMap(fn ($r) => $r['subjects'])->unique('id')->sortBy('name')->values();
        $rows = [];

        foreach (collect($perExam)->first()['rows'] as $first) {
            $studentId = $first['student_id'];
            $cells = [];
            $points = [];
            foreach ($subjects as $subject) {
                $sum = $w = 0.0;
                foreach ($perExam as $id => $r) {
                    $mark = collect($r['rows'])->firstWhere('student_id', $studentId)['cells'][$subject['id']]['marks'] ?? null;
                    if ($mark !== null) {
                        $sum += $mark * $weights[$id];
                        $w += $weights[$id];
                    }
                }
                $value = $w > 0 ? round($sum / $w, 2) : null;
                $band = $this->grading->gradeFor($value);
                $cells[$subject['id']] = ['marks' => $value, 'grade' => $band?->grade];
                if ($band?->points !== null && $value !== null) {
                    $points[] = $band->points;
                }
            }

            $values = collect($cells)->pluck('marks')->filter(fn ($v) => $v !== null);
            $average = $values->isNotEmpty() ? round($values->avg(), 2) : null;
            $rows[] = [
                'student_id' => $studentId,
                'full_name' => $first['full_name'],
                'admission_number' => $first['admission_number'],
                'cells' => $cells,
                'per_exam' => collect($perExam)->map(fn ($r) => collect($r['rows'])->firstWhere('student_id', $studentId)['average'] ?? null)->all(),
                'average' => $average,
                'grade' => $this->grading->gradeFor($average)?->grade,
                'division' => $this->results->division($points),
                'position' => null,
            ];
        }

        $order = collect($rows)->filter(fn ($row) => $row['average'] !== null)->sortByDesc('average')->values();
        $position = 0;
        $previous = null;
        foreach ($order as $i => $row) {
            if ($previous === null || $row['average'] !== $previous) {
                $position = $i + 1;
                $previous = $row['average'];
            }
            foreach ($rows as &$target) {
                if ($target['student_id'] === $row['student_id']) {
                    $target['position'] = $position;
                }
            }
            unset($target);
        }

        usort($rows, fn ($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX) ?: strcasecmp($a['full_name'], $b['full_name']));

        return [
            'class' => ['id' => $class->id, 'label' => ExamScope::classLabel($class)],
            'exams' => collect($ids)->map(fn ($id) => ['id' => $id, 'name' => $exams[$id]->name, 'weight' => $weights[$id]])->all(),
            'subjects' => $subjects->map(fn ($s) => ['id' => $s['id'], 'name' => $s['name']])->all(),
            'rows' => $rows,
        ];
    }

    private function brief(array $row): array
    {
        return ['student_id' => $row['student_id'], 'full_name' => $row['full_name'], 'average' => $row['average'], 'grade' => $row['grade'], 'position' => $row['position'], 'division' => $row['division']];
    }
}
