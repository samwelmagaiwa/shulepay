<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserSchoolAccessController extends Controller
{
    /** GET /user-school-access/{user} — list accessible schools for a user */
    public function show(User $user): JsonResponse
    {
        $schools = $user->accessibleSchools()->select('schools.id', 'schools.name', 'schools.level')->get();

        return response()->json([
            'user_id' => $user->id,
            'primary_school_id' => $user->school_id,
            'has_multi_school' => $user->hasPermissionTo('multi_school'),
            'accessible_schools' => $schools,
        ]);
    }

    /** POST /user-school-access — grant access to a school */
    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'school_id' => 'required|exists:schools,id',
        ]);

        $user = User::findOrFail($data['user_id']);
        $school = School::findOrFail($data['school_id']);

        // Owners may only grant access to schools they themselves can access
        $actor = auth()->user();
        if (! $actor->hasRole('superadmin') && ! $actor->canAccessSchool($school->id)) {
            return response()->json(['message' => 'Huna ruhusa ya kutoa ufikiaji wa shule hii.'], 403);
        }

        // Ensure multi_school is not in forbidden_permissions (would block the permission check)
        $forbidden = $user->forbidden_permissions ?? [];
        if (in_array('multi_school', $forbidden, true)) {
            $user->forbidden_permissions = array_values(array_diff($forbidden, ['multi_school']));
            $user->save();
        }

        // Ensure the user has the multi_school permission
        if (! $user->spatieHasPermissionTo('multi_school')) {
            $perm = Permission::firstOrCreate(['name' => 'multi_school', 'guard_name' => 'web']);
            $user->givePermissionTo($perm);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        // Add the school access (ignore if already exists)
        $user->accessibleSchools()->syncWithoutDetaching([$school->id]);

        AuditLogger::log('user.school_access_granted', $user, [
            'school_id' => $school->id, 'school_name' => $school->name,
        ]);

        return response()->json([
            'message' => "Ruhusa ya shule '{$school->name}' imepewa {$user->name}.",
            'has_multi_school' => true,
            'accessible_schools' => $user->accessibleSchools()->select('schools.id', 'schools.name', 'schools.level')->get(),
        ]);
    }

    /** DELETE /user-school-access/{user}/{school} — revoke access to a school */
    public function revoke(User $user, School $school): JsonResponse
    {
        $user->accessibleSchools()->detach($school->id);

        // If no extra schools remain and primary school covers it, revoke the permission
        $remaining = $user->accessibleSchools()->count();
        if ($remaining === 0) {
            $user->revokePermissionTo('multi_school');
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        AuditLogger::log('user.school_access_revoked', $user, [
            'school_id' => $school->id, 'school_name' => $school->name,
        ]);

        $hasMulti = $remaining > 0;

        return response()->json([
            'message' => "Ruhusa ya shule '{$school->name}' imeondolewa kwa {$user->name}.",
            'has_multi_school' => $hasMulti,
            'accessible_schools' => $user->accessibleSchools()->select('schools.id', 'schools.name', 'schools.level')->get(),
        ]);
    }
}
