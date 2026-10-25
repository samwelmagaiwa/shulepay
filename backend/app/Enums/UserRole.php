<?php

namespace App\Enums;

/**
 * Canonical role names — single source of truth for the entire backend.
 *
 * Every role string used in middleware, model helpers, seeders, and controllers
 * must come from here. Never use a bare string like 'teacher_pri' in business logic.
 */
enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case Owner = 'owner';
    case Accountant = 'accountant';
    case HeadTeacher = 'head_teacher';    // Primary school
    case Headmaster = 'headmaster';      // Secondary school
    case AcademicPri = 'academic_pri';    // Academic coordinator — primary
    case AcademicSec = 'academic_sec';    // Academic coordinator — secondary
    case TeacherPri = 'teacher_pri';     // Class teacher — primary
    case TeacherSec = 'teacher_sec';     // Class teacher — secondary
    case Parent = 'parent';

    // ── Display ───────────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Owner => 'Owner',
            self::Accountant => 'Accountant',
            self::HeadTeacher => 'Head Teacher',
            self::Headmaster => 'Headmaster',
            self::AcademicPri => 'Academic Coordinator (Primary)',
            self::AcademicSec => 'Academic Coordinator (Secondary)',
            self::TeacherPri => 'Class Teacher (Primary)',
            self::TeacherSec => 'Class Teacher (Secondary)',
            self::Parent => 'Parent / Guardian',
        };
    }

    // ── Grouping helpers ──────────────────────────────────────────────────────

    /** All class teachers (primary + secondary). */
    public static function classTeachers(): array
    {
        return [self::TeacherPri->value, self::TeacherSec->value];
    }

    /** All academic-coordinator roles. */
    public static function academicCoordinators(): array
    {
        return [self::AcademicPri->value, self::AcademicSec->value];
    }

    /** All head-of-school roles. */
    public static function schoolHeads(): array
    {
        return [self::HeadTeacher->value, self::Headmaster->value];
    }

    /** Every role that can log in as a teaching/academic staff member. */
    public static function teachingStaff(): array
    {
        return [
            self::TeacherPri->value,
            self::TeacherSec->value,
            self::AcademicPri->value,
            self::AcademicSec->value,
            self::HeadTeacher->value,
            self::Headmaster->value,
        ];
    }

    /** Roles with access to financial data (invoices, payments, reports). */
    public static function financeStaff(): array
    {
        return [
            self::Accountant->value,
            self::Owner->value,
            self::SuperAdmin->value,
        ];
    }

    /** Roles that may mark / view attendance registers. */
    public static function attendanceMarkers(): array
    {
        return [
            ...self::teachingStaff(),
            ...self::financeStaff(),  // accountant/owner can view attendance too
        ];
    }

    /** Roles that may enter exam marks (and read results). */
    public static function examStaff(): array
    {
        return [...self::teachingStaff(), self::Owner->value];
    }

    /** Roles that set up exams, subjects and the grading scale. */
    public static function examManagers(): array
    {
        return [
            ...self::schoolHeads(),
            ...self::academicCoordinators(),
            self::Owner->value,
        ];
    }

    /** Roles that approve and publish results to parents (heads of school). */
    public static function examPublishers(): array
    {
        return [...self::schoolHeads(), ...self::academicCoordinators(), self::Owner->value];
    }

    /** Roles that create and maintain the school's classes. */
    public static function classKeepers(): array
    {
        return [...self::adminStaff(), ...self::academicCoordinators()];
    }

    /** Roles that keep the school calendar (academic years and terms). */
    public static function calendarKeepers(): array
    {
        return [...self::financeStaff(), ...self::academicCoordinators()];
    }

    /** Roles with school-administration privileges. */
    public static function adminStaff(): array
    {
        return [self::Owner->value, self::SuperAdmin->value];
    }

    /** All roles that exist in the system (for seeders). */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    // ── Route middleware helper ───────────────────────────────────────────────

    /**
     * Build a pipe-delimited string for use in Spatie's route middleware.
     *
     *   Route::middleware('role:'.UserRole::guard(UserRole::teachingStaff()))
     */
    public static function guard(array $roles): string
    {
        // SuperAdmin is appended here rather than repeated in every list above.
        // teachingStaff() omitted it, so routes guarded by it - the academic and
        // attendance groups - refused a superadmin outright. Adding it to each
        // list would fix today's gap and leave the next one to chance; adding it
        // at the single point where a guard string is built cannot be forgotten.
        //
        // Scope is deliberate: this shapes ROUTE middleware only. The lists
        // themselves stay honest about which roles the label really describes,
        // so anything else reading teachingStaff() still gets teachers.
        $roles[] = self::SuperAdmin->value;

        return implode('|', array_unique($roles));
    }

    // ── Instance predicates ───────────────────────────────────────────────────

    public function isTeachingStaff(): bool
    {
        return in_array($this->value, self::teachingStaff(), true);
    }

    public function isFinanceStaff(): bool
    {
        return in_array($this->value, self::financeStaff(), true);
    }

    public function canMarkAttendance(): bool
    {
        return in_array($this->value, self::attendanceMarkers(), true);
    }

    public function isAdminStaff(): bool
    {
        return in_array($this->value, self::adminStaff(), true);
    }
}
