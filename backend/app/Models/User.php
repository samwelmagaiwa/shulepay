<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles {
        hasPermissionTo as spatieHasPermissionTo;
    }

    protected $fillable = [
        'name', 'email', 'password', 'must_change_password', 'school_id', 'phone', 'avatar',
        '2fa_enabled', 'is_active', 'deactivated_at', 'deactivation_reason',
        'forbidden_permissions',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        '2fa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'deactivated_at' => 'datetime',
        'forbidden_permissions' => 'array',
    ];

    // ── Permission overrides ──────────────────────────────────────────────────

    /**
     * Superadmin always passes. Everyone else is checked against their
     * forbidden_permissions list before falling through to Spatie.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        $permName = $permission instanceof Permission
            ? $permission->name
            : (string) $permission;

        if (in_array($permName, $this->forbidden_permissions ?? [], true)) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /** All effective permissions: role + direct grants, minus forbidden list. */
    public function effectivePermissions(): array
    {
        if ($this->hasRole(UserRole::SuperAdmin->value)) {
            return Permission::all()->pluck('name')->toArray();
        }

        return array_values(
            array_diff(
                $this->getAllPermissions()->pluck('name')->toArray(),
                $this->forbidden_permissions ?? []
            )
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /** Schools explicitly granted via multi-school access (pivot). */
    public function accessibleSchools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'user_school_access')->withTimestamps();
    }

    // ── School-access helpers ─────────────────────────────────────────────────

    /**
     * True if the user may act on the given school.
     * Superadmin always passes. Others must match their primary school
     * or hold an explicit grant in user_school_access.
     */
    public function canAccessSchool(int $schoolId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ((int) $this->school_id === $schoolId) {
            return true;
        }

        return \DB::table('user_school_access')
            ->where('user_id', $this->id)
            ->where('school_id', $schoolId)
            ->exists();
    }

    /**
     * IDs of all schools this user can act on.
     * Empty array means "all schools" (superadmin only).
     */
    public function allAccessibleSchoolIds(): array
    {
        if ($this->isSuperAdmin()) {
            return [];
        }

        $primary = $this->school_id ? [(int) $this->school_id] : [];
        $extra = \DB::table('user_school_access')
            ->where('user_id', $this->id)
            ->pluck('school_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return array_values(array_unique(array_merge($primary, $extra)));
    }

    // ── Role helpers (delegate to UserRole enum) ──────────────────────────────

    /** Returns the user's primary UserRole enum case, or null if unrecognised. */
    public function userRole(): ?UserRole
    {
        $roleName = $this->roles->first()?->name;

        return $roleName ? UserRole::tryFrom($roleName) : null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin->value);
    }

    public function isOwner(): bool
    {
        // Superadmin carries owner-level privileges as well.
        return $this->hasAnyRole([UserRole::Owner->value, UserRole::SuperAdmin->value]);
    }

    public function isAccountant(): bool
    {
        return $this->hasAnyRole([UserRole::Accountant->value, UserRole::SuperAdmin->value]);
    }

    public function isParent(): bool
    {
        return $this->hasRole(UserRole::Parent->value);
    }

    /** Class teacher — primary or secondary. */
    public function isTeacher(): bool
    {
        return $this->hasAnyRole(UserRole::classTeachers());
    }

    /** Head teacher (primary) or headmaster (secondary). */
    public function isSchoolHead(): bool
    {
        return $this->hasAnyRole(UserRole::schoolHeads());
    }

    /** Head teacher — primary schools. */
    public function isHeadTeacher(): bool
    {
        return $this->hasRole(UserRole::HeadTeacher->value);
    }

    /** Headmaster — secondary schools. */
    public function isHeadmaster(): bool
    {
        return $this->hasRole(UserRole::Headmaster->value);
    }

    /** Academic coordinator — primary or secondary. */
    public function isAcademicCoordinator(): bool
    {
        return $this->hasAnyRole(UserRole::academicCoordinators());
    }

    /** Any teaching or academic-staff role. */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(UserRole::teachingStaff());
    }

    /** True for any role that may mark or view attendance. */
    public function canMarkAttendance(): bool
    {
        return $this->hasAnyRole(UserRole::attendanceMarkers());
    }

    /** True for any role that has access to financial data. */
    public function isFinanceStaff(): bool
    {
        return $this->hasAnyRole(UserRole::financeStaff());
    }
}
