<?php

namespace App\Services\Exam;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which subject a teacher teaches in which class. Classroom teachers may only
 * enter marks for their own assignments; coordinators, heads and the owner may
 * enter marks for anything.
 */
class TeacherAssignmentService
{
    /** Roles whose marks entry is limited to their assignments. */
    public static function restrictedRoles(): array
    {
        return [UserRole::TeacherPri->value, UserRole::TeacherSec->value];
    }

    public function isRestricted(User $user): bool
    {
        return in_array($user->userRole()?->value, self::restrictedRoles(), true);
    }

    /** @throws ExamException when a classroom teacher enters marks outside their assignments */
    public function assertMayEnter(User $user, SchoolClass $class, Subject $subject): void
    {
        if (! $this->isRestricted($user)) {
            return;
        }

        $assigned = TeacherAssignment::query()
            ->where('user_id', $user->id)
            ->where('school_class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->exists();

        if (! $assigned) {
            throw ExamException::forbidden(
                __('exams.err.teacher_not_assigned', ['subject' => $subject->name, 'class' => ExamScope::classLabel($class)]),
                'TEACHER_NOT_ASSIGNED',
            );
        }
    }

    /**
     * What this user may enter marks for. Unrestricted users get `restricted: false`
     * and the UI offers every class and subject.
     *
     * @return array{restricted: bool, assignments: array<int, array{school_class_id: int, subject_id: int}>}
     */
    public function forUser(User $user): array
    {
        $restricted = $this->isRestricted($user);

        return [
            'restricted' => $restricted,
            'assignments' => $restricted
                ? TeacherAssignment::where('user_id', $user->id)
                    ->get(['school_class_id', 'subject_id'])
                    ->map(fn ($a) => ['school_class_id' => $a->school_class_id, 'subject_id' => $a->subject_id])
                    ->all()
                : [],
        ];
    }

    /** Every classroom teacher of the school with their assignments. */
    public function teachers(): Collection
    {
        return ExamScope::whereSchool(User::query(), 'users.school_id')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::restrictedRoles()))
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'assignments' => TeacherAssignment::where('user_id', $teacher->id)
                    ->get(['school_class_id', 'subject_id'])
                    ->map(fn ($a) => ['school_class_id' => $a->school_class_id, 'subject_id' => $a->subject_id])
                    ->all(),
            ]);
    }

    /**
     * Replace a teacher's assignments. Every pair must be a subject the class
     * actually sits, in a class of this school.
     *
     * @param  array<int, array{school_class_id: int, subject_id: int}>  $pairs
     */
    public function sync(User $teacher, array $pairs): int
    {
        if (! in_array($teacher->userRole()?->value, self::restrictedRoles(), true)) {
            throw ExamException::invalid(__('exams.err.not_a_teacher'), 'NOT_A_TEACHER');
        }

        $pairs = collect($pairs)->map(fn ($p) => [(int) $p['school_class_id'], (int) $p['subject_id']])->unique(fn ($p) => implode('-', $p));

        $invalid = [];
        foreach ($pairs->groupBy(0) as $classId => $group) {
            $class = ExamScope::classes()->find($classId);
            $taught = $class?->subjects()->pluck('subjects.id') ?? collect();

            foreach ($group as [, $subjectId]) {
                if (! $class || ! $taught->contains($subjectId)) {
                    $invalid[] = ['school_class_id' => $classId, 'subject_id' => $subjectId];
                }
            }
        }

        if ($invalid) {
            throw ExamException::invalid(
                __('exams.err.subjects_not_in_classes'),
                'SUBJECT_NOT_IN_CLASS',
                ['pairs' => $invalid],
            );
        }

        DB::transaction(function () use ($teacher, $pairs) {
            TeacherAssignment::where('user_id', $teacher->id)->delete();
            $pairs->each(fn ($p) => TeacherAssignment::create([
                'user_id' => $teacher->id, 'school_class_id' => $p[0], 'subject_id' => $p[1],
            ]));
        });

        return $pairs->count();
    }
}
