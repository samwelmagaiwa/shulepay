<?php

namespace App\Services\Exam;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves "which school am I in" for the exam module and applies it to the
 * models that do not scope themselves.
 *
 * The app runs in two modes: a per-school tenant database (the whole database is
 * one school, so nothing needs filtering and there is no school_id column), and
 * a shared central database where rows carry school_id. Classes and enrollments
 * are not scoped by a model trait, so the module filters them here — and only in
 * central mode, because the column does not exist in a tenant database.
 */
final class ExamScope
{
    public static function isTenantMode(): bool
    {
        return app()->bound('tenancy.initialized');
    }

    public static function school(): ?School
    {
        if (app()->bound('tenancy.school')) {
            return app('tenancy.school');
        }

        return app()->bound('active_school') ? app('active_school') : null;
    }

    /** School id to filter unscoped central tables by; null in tenant mode. */
    public static function centralSchoolId(): ?int
    {
        return self::isTenantMode() ? null : self::school()?->id;
    }

    /**
     * The schools this request may read: the active school, else every school the signed-in
     * user has access to ("all schools" mode must never mean "every school in the database").
     * Empty means unrestricted (a superadmin in all-schools mode, or no signed-in user in a console run).
     *
     * @return list<int>
     */
    public static function schoolIds(): array
    {
        if (self::isTenantMode()) {
            return [];
        }
        $user = auth()->user();

        return $user ? SchoolScope::ids($user) : (self::school() ? [self::school()->id] : []);
    }

    /**
     * Restrict a query on a school-owned table to the schools this request may read. A no-op in
     * tenant mode, where the table has no school_id and the database is the school.
     */
    public static function whereSchool(mixed $query, string $column = 'school_id'): mixed
    {
        $ids = self::schoolIds();

        return $ids ? $query->whereIn($column, $ids) : $query;
    }

    public static function classes(): Builder
    {
        return self::whereSchool(SchoolClass::query(), 'school_classes.school_id');
    }

    /** The classes an exam covers: every class, or only the ones it was limited to. */
    public static function classesFor(Exam $exam): Builder
    {
        return self::classes()->when(! $exam->all_classes, fn (Builder $q) => $q->whereIn(
            'school_classes.id',
            $exam->classes()->pluck('school_classes.id'),
        ));
    }

    /** @throws ExamException when the class does not sit this exam */
    public static function assertCovers(Exam $exam, SchoolClass $class): void
    {
        if (! $exam->coversClass($class)) {
            throw ExamException::invalid(
                __('exams.err.class_not_in_exam', ['exam' => $exam->name, 'class' => self::classLabel($class)]),
                'CLASS_NOT_IN_EXAM',
            );
        }
    }

    public static function activeEnrollments(): Builder
    {
        return self::whereSchool(
            Enrollment::query()->where('enrollments.status', 'active'),
            'enrollments.school_id',
        );
    }

    /** A pupil's name with single spaces (fullName() leaves a gap when there is no middle name). */
    public static function personName(Student $student): string
    {
        return preg_replace('/\s+/', ' ', trim($student->fullName()));
    }

    /** "Darasa la 1" or "Form 2 A" — the stream column only exists in tenant databases. */
    public static function classLabel(SchoolClass $class): string
    {
        return trim($class->name.' '.($class->stream ?? ''));
    }
}
