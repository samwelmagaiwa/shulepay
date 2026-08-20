<?php

namespace App\Models;

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
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'school_id', 'phone', 'avatar', '2fa_enabled',
        'is_active', 'deactivated_at', 'deactivation_reason', 'forbidden_permissions'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        '2fa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'forbidden_permissions' => 'array',
    ];

    /**
     * Override Spatie's hasPermissionTo so that superadmin-forbidden permissions
     * are denied even when the user's role would normally grant them.
     * Superadmin accounts are never restricted.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }
        $permName = $permission instanceof Permission
            ? $permission->name : (string) $permission;

        $forbidden = $this->forbidden_permissions ?? [];
        if (in_array($permName, $forbidden, true)) {
            return false;
        }

        return parent::hasPermissionTo($permission, $guardName);
    }

    /** All effective permissions: role + direct, minus forbidden */
    public function effectivePermissions(): array
    {
        if ($this->hasRole('superadmin')) {
            return Permission::all()->pluck('name')->toArray();
        }
        $all = $this->getAllPermissions()->pluck('name')->toArray();
        $forbidden = $this->forbidden_permissions ?? [];

        return array_values(array_diff($all, $forbidden));
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Schools explicitly granted via multi_school access (pivot table) */
    public function accessibleSchools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'user_school_access')->withTimestamps();
    }

    /**
     * True if the user may act on the given school.
     * Superadmins always pass. Others must match their primary school
     * OR have an explicit grant in user_school_access (requires multi_school permission).
     */
    public function canAccessSchool(int $schoolId): bool
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }
        if ($this->school_id === $schoolId) {
            return true;
        }
        if ($this->hasPermissionTo('multi_school')) {
            return $this->accessibleSchools()->where('school_id', $schoolId)->exists();
        }

        return false;
    }

    /** IDs of all schools this user can act on (primary + granted extras) */
    public function allAccessibleSchoolIds(): array
    {
        $ids = $this->school_id ? [$this->school_id] : [];
        if ($this->hasRole('superadmin')) {
            return [];  // empty = "all schools" for superadmin callers
        }
        if ($this->hasPermissionTo('multi_school')) {
            $extra = $this->accessibleSchools()->pluck('schools.id')->toArray();
            $ids = array_unique(array_merge($ids, $extra));
        }

        return array_values($ids);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isAccountant(): bool
    {
        return $this->hasRole('accountant');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner') || $this->hasRole('superadmin');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    /** Class teacher — primary or secondary */
    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    /** Head Teacher — primary schools */
    public function isHeadTeacher(): bool
    {
        return $this->hasRole('head_teacher');
    }

    /** Headmaster/Headmistress — secondary schools */
    public function isHeadmaster(): bool
    {
        return $this->hasRole('headmaster');
    }

    /** Subject / academic teacher — primary or secondary */
    public function isAcademicTeacher(): bool
    {
        return $this->hasRole('academic_teacher');
    }

    /** Any teaching-staff role */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['teacher', 'head_teacher', 'headmaster', 'academic_teacher']);
    }

    /** Roles allowed to mark attendance */
    public function canMarkAttendance(): bool
    {
        return $this->hasAnyRole(['teacher', 'head_teacher', 'headmaster', 'academic_teacher', 'accountant', 'owner', 'superadmin']);
    }
}
