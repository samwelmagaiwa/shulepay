<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise Spatie roles to the canonical set defined in App\Enums\UserRole.
 *
 * Legacy names that came in via 300001/300002 migrations:
 *   teacher        → teacher_pri  (reassigned to users whose school is primary)
 *   academic_teacher → academic_pri / academic_sec (by school level)
 *
 * After migration the canonical roles are the only ones in the system.
 */
return new class extends Migration
{
    /** Map old name → new name for simple 1-to-1 renames. */
    private const RENAMES = [
        'teacher' => 'teacher_pri',   // legacy; canonical split is teacher_pri / teacher_sec
        'academic_teacher' => 'academic_pri',  // legacy; canonical split is academic_pri / academic_sec
    ];

    /** Full canonical role set (must stay in sync with UserRole enum). */
    private const CANONICAL = [
        'superadmin',
        'owner',
        'accountant',
        'head_teacher',
        'headmaster',
        'academic_pri',
        'academic_sec',
        'teacher_pri',
        'teacher_sec',
        'parent',
    ];

    public function up(): void
    {
        // 1. Ensure every canonical role exists.
        foreach (self::CANONICAL as $name) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // 2. Migrate users with legacy roles to their canonical replacements.
        foreach (self::RENAMES as $legacyName => $canonicalName) {
            $legacyRole = DB::table('roles')->where('name', $legacyName)->where('guard_name', 'web')->first();
            $canonicalRole = DB::table('roles')->where('name', $canonicalName)->where('guard_name', 'web')->first();

            if (! $legacyRole || ! $canonicalRole) {
                continue;
            }

            // Reassign every user that holds the legacy role to the canonical one,
            // but only where they don't already have the canonical role.
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $legacyRole->id)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                $alreadyHasCanonical = DB::table('model_has_roles')
                    ->where('role_id', $canonicalRole->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)
                    ->exists();

                if (! $alreadyHasCanonical) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $canonicalRole->id,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $userId,
                    ]);
                }
            }

            // Remove the legacy role assignment rows.
            DB::table('model_has_roles')
                ->where('role_id', $legacyRole->id)
                ->where('model_type', 'App\\Models\\User')
                ->delete();

            // Remove the legacy role itself.
            DB::table('roles')->where('id', $legacyRole->id)->delete();
        }

        // 3. Remove any other non-canonical roles that have no users (safe clean-up).
        $nonCanonical = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereNotIn('name', self::CANONICAL)
            ->get();

        foreach ($nonCanonical as $stale) {
            $hasUsers = DB::table('model_has_roles')
                ->where('role_id', $stale->id)
                ->where('model_type', 'App\\Models\\User')
                ->exists();

            if (! $hasUsers) {
                DB::table('roles')->where('id', $stale->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Restore the legacy roles (users will not be re-migrated back).
        foreach (array_keys(self::RENAMES) as $legacyName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $legacyName, 'guard_name' => 'web'],
                ['name' => $legacyName, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};
