<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    // Roles available for any school level
    private const ROLES_ALL = ['owner', 'accountant'];

    // Roles restricted to primary schools only
    private const ROLES_PRIMARY = ['teacher_pri', 'academic_pri', 'head_teacher'];

    // Roles restricted to secondary schools only
    private const ROLES_SECONDARY = ['teacher_sec', 'academic_sec', 'headmaster'];

    private function allAllowedRoles(): array
    {
        return array_merge(self::ROLES_ALL, self::ROLES_PRIMARY, self::ROLES_SECONDARY);
    }

    private function validateRoleForSchool(string $role, int $schoolId): void
    {
        $school = School::find($schoolId);
        $level = $school?->level?->value;

        if (in_array($role, self::ROLES_PRIMARY) && $level !== 'primary') {
            throw ValidationException::withMessages([
                'role' => "Role '$role' is only allowed for primary schools.",
            ]);
        }

        if (in_array($role, self::ROLES_SECONDARY) && $level !== 'secondary') {
            throw ValidationException::withMessages([
                'role' => "Role '$role' is only allowed for secondary schools.",
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        // Respect the active school from the nav switcher (X-School-Id header)
        $activeSchool = app()->bound('active_school') ? app('active_school') : null;
        $schoolId     = $activeSchool?->id ?? $authUser->school_id;

        // Superadmin with no active school → show all staff across all schools
        if ($authUser->hasRole('superadmin') && ! $activeSchool) {
            $users = User::with(['roles', 'school:id,name'])
                ->withCount('accessibleSchools')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $this->allAllowedRoles()))
                ->orderBy('name')
                ->paginate(100);
        } else {
            $users = User::with(['roles', 'school:id,name'])
                ->withCount('accessibleSchools')
                ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $this->allAllowedRoles()))
                ->orderBy('name')
                ->paginate(100);
        }

        // Map accessible_schools_count → multi_school_count for clarity
        $users->getCollection()->transform(function ($u) {
            $u->multi_school_count = $u->accessible_schools_count ?? 0;
            return $u;
        });

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|in:'.implode(',', $this->allAllowedRoles()),
        ]);

        $schoolId = auth()->user()->school_id;

        $this->validateRoleForSchool($data['role'], $schoolId);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'school_id' => $schoolId,
            'phone' => $data['phone'] ?? null,
        ]);

        $user->syncRoles([$data['role']]);

        AuditLogger::log('user_created', $user, [
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $data['role'],
                'school_id' => $schoolId,
            ],
        ]);

        return response()->json($user->load('roles'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->school_id === auth()->user()->school_id, 403, 'Forbidden.');

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'password' => 'sometimes|required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|required|string|in:'.implode(',', $this->allAllowedRoles()),
        ]);

        $before = $user->only(['name', 'email', 'school_id']);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        $user->save();

        if (isset($data['role'])) {
            $this->validateRoleForSchool($data['role'], $user->school_id);
            $user->syncRoles([$data['role']]);
        }

        AuditLogger::log('user_updated', $user, [
            'before' => $before,
            'after' => $user->fresh()->only(['name', 'email', 'school_id']),
        ]);

        return response()->json($user->load('roles'), 200);
    }

    public function destroy(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }

        AuditLogger::log('user_deleted', $user, [
            'before' => $user->only(['name', 'email', 'school_id']),
        ]);

        $user->delete();

        return response()->json(null, 204);
    }
}
