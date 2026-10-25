<?php

namespace App\Services\Exam;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;

/**
 * The order the exam module has to be set up in. Each step needs the one before
 * it, so the first unfinished step is what the user should do next.
 */
class ExamReadinessService
{
    public function __construct(private readonly ExamResultsService $results) {}

    /**
     * @return array{steps: array<int, array>, next: ?string, ready: bool, complete: bool}
     */
    public function check(): array
    {
        $classes = ExamScope::classes()->get();
        $withSubjects = $classes->filter(fn ($class) => $this->results->subjectsFor($class)->isNotEmpty())->count();

        $terms = Term::query()->whereHas('academicYear', fn ($y) => ExamScope::whereSchool($y));

        $steps = [
            $this->step('academic_year', ExamScope::whereSchool(AcademicYear::query())->count()),
            $this->step('term', $terms->count()),
            $this->step('classes', $classes->count()),
            $this->step('subjects', Subject::query()->active()->count()),
            // One class with subjects is enough to start; the rest can follow.
            $this->step('class_subjects', $withSubjects, $classes->count()),
            // Classroom teachers can only enter marks for what they are assigned. A school without any
            // classroom teacher yet is never blocked by this step.
            $this->teachersStep(),
        ];

        // A step is locked until every step before it is done.
        $locked = false;
        foreach ($steps as &$step) {
            $step['locked'] = $locked;
            $locked = $locked || ! $step['done'];
        }
        unset($step);

        $next = collect($steps)->firstWhere('done', false);
        // The teachers step is a recommendation: exams can be created without it, so it does not block.
        $core = collect($steps)->where('key', '!=', 'teachers')->every('done');

        return ['steps' => $steps, 'next' => $next['key'] ?? null, 'ready' => $core, 'complete' => $next === null];
    }

    private function teachersStep(): array
    {
        $teachers = ExamScope::whereSchool(User::query(), 'users.school_id')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [UserRole::TeacherPri->value, UserRole::TeacherSec->value]))
            ->count();
        $assigned = TeacherAssignment::query()->distinct()->count('user_id');

        return ['key' => 'teachers', 'done' => $teachers === 0 || $assigned >= 1, 'count' => $assigned, 'total' => $teachers];
    }

    private function step(string $key, int $count, ?int $total = null): array
    {
        return ['key' => $key, 'done' => $count >= 1, 'count' => $count, 'total' => $total];
    }
}
