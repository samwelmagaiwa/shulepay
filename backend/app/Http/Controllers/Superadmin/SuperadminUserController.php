<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperadminUserController extends Controller
{
    private const PROTECTED_IDS = [];   // can extend to protect specific user IDs

    // ── List all users across all schools ────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'permissions', 'school'])
            ->orderBy('name');

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->string('role')));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $q = $request->string('search');
            $query->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }

        $perPage = min((int) $request->input('per_page', 25), 200);

        return response()->json($query->paginate($perPage));
    }

    // ── Create user (superadmin can assign any role + direct permissions) ────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'school_id' => 'nullable|integer|exists:schools,id',
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'school_id' => $data['school_id'] ?? null,
            'is_active' => true,
        ]);

        $user->syncRoles([$data['role']]);

        if (! empty($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        AuditLogger::log('superadmin_user_created', $user, [
            'after' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $data['role'],
                'school_id' => $data['school_id'] ?? null,
                'permissions' => $data['permissions'] ?? [],
                'created_by' => auth()->user()->name,
            ],
        ]);

        return response()->json($user->load(['roles', 'permissions', 'school']), 201);
    }

    // ── Show single user ─────────────────────────────────────────────────────
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load(['roles', 'permissions', 'school']));
    }

    // ── Update user details + role + permissions ─────────────────────────────
    public function update(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($user);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,'.$user->id,
            'password' => 'sometimes|required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'school_id' => 'nullable|integer|exists:schools,id',
            'role' => 'sometimes|required|string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
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
        if (array_key_exists('school_id', $data)) {
            $user->school_id = $data['school_id'];
        }

        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        if (array_key_exists('permissions', $data)) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        AuditLogger::log('superadmin_user_updated', $user, [
            'before' => $before,
            'after' => $user->fresh()->only(['name', 'email', 'school_id']),
            'updated_by' => auth()->user()->name,
        ]);

        return response()->json($user->load(['roles', 'permissions', 'school']));
    }

    // ── Freeze / deactivate a user ───────────────────────────────────────────
    public function deactivate(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($user);
        $this->guardSuperadmin($user);

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (! $user->is_active) {
            return response()->json(['message' => 'User is already deactivated.'], 422);
        }

        // Revoke all active tokens immediately — no further API access
        $user->tokens()->delete();

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $data['reason'] ?? null,
        ]);

        AuditLogger::log('user_deactivated', $user, [
            'reason' => $data['reason'] ?? null,
            'deactivated_by' => auth()->user()->name,
        ]);

        return response()->json([
            'message' => "User '{$user->name}' has been deactivated.",
            'user' => $user->load(['roles', 'school']),
        ]);
    }

    // ── Reactivate a frozen user ─────────────────────────────────────────────
    public function reactivate(User $user): JsonResponse
    {
        $this->guardSelf($user);

        if ($user->is_active) {
            return response()->json(['message' => 'User is already active.'], 422);
        }

        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);

        AuditLogger::log('user_reactivated', $user, [
            'reactivated_by' => auth()->user()->name,
        ]);

        return response()->json([
            'message' => "User '{$user->name}' has been reactivated.",
            'user' => $user->load(['roles', 'school']),
        ]);
    }

    // ── Sync only permissions (without changing role) ────────────────────────
    public function syncPermissions(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
            'forbidden_permissions' => 'nullable|array',
            'forbidden_permissions.*' => 'string|exists:permissions,name',
        ]);

        // Guard: cannot assign permissions that belong to no known group (prevents DB pollution)
        $known = collect(RolePermissionController::allPermissions())->flatten()->all();
        $unknown = array_diff($data['permissions'], $known);
        if (! empty($unknown)) {
            return response()->json([
                'message' => 'Ruhusa zisizojulikana: '.implode(', ', $unknown),
            ], 422);
        }

        $forbidList = array_values(array_unique($data['forbidden_permissions'] ?? []));

        // Guard forbidden perms against the known application set (same as direct perms)
        $unknownForbid = array_diff($forbidList, $known);
        if (! empty($unknownForbid)) {
            return response()->json([
                'message' => 'Ruhusa zisizojulikana katika orodha ya kukatazwa: '.implode(', ', $unknownForbid),
            ], 422);
        }

        // Conflict check: same perm cannot be granted and forbidden simultaneously
        $conflict = array_intersect($data['permissions'], $forbidList);
        if (! empty($conflict)) {
            return response()->json([
                'message' => 'Mgongano: ruhusa moja haiwezi kupewa na kukatazwa kwa wakati mmoja: '.implode(', ', $conflict),
            ], 422);
        }

        $hadMultiSchool = $user->hasPermissionTo('multi_school');
        $removingMultiSchool = $hadMultiSchool && (
            ! in_array('multi_school', $data['permissions'], true)
            || in_array('multi_school', $forbidList, true)
        );

        $user->syncPermissions($data['permissions']);
        $user->update(['forbidden_permissions' => $forbidList]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // If multi_school was removed, revoke all explicit school access grants
        if ($removingMultiSchool) {
            $user->accessibleSchools()->detach();
        }

        AuditLogger::log('user_permissions_updated', $user, [
            'permissions' => $data['permissions'],
            'forbidden_permissions' => $forbidList,
            'updated_by' => auth()->user()->name,
        ]);

        $user->refresh()->load(['roles', 'permissions', 'school']);

        return response()->json(array_merge(
            $user->toArray(),
            ['forbidden_permissions' => $user->forbidden_permissions ?? []]
        ));
    }

    // ── Delete user permanently ──────────────────────────────────────────────
    public function destroy(User $user): JsonResponse
    {
        $this->guardSelf($user);
        $this->guardSuperadmin($user);

        $user->tokens()->delete();

        AuditLogger::log('superadmin_user_deleted', $user, [
            'before' => $user->only(['name', 'email', 'school_id']),
            'deleted_by' => auth()->user()->name,
        ]);

        $user->delete();

        return response()->json(null, 204);
    }

    // ── Restrict/forbid specific permissions for a user ─────────────────────
    public function restrictPermissions(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($user);
        $this->guardSuperadmin($user);

        $data = $request->validate([
            'forbidden_permissions' => 'required|array',
            'forbidden_permissions.*' => 'string|exists:permissions,name',
        ]);

        $known = collect(RolePermissionController::allPermissions())->flatten()->all();
        $forbidList = array_values(array_unique($data['forbidden_permissions']));

        // Guard against unknown permissions
        $unknown = array_diff($forbidList, $known);
        if (! empty($unknown)) {
            return response()->json([
                'message' => 'Ruhusa zisizojulikana: '.implode(', ', $unknown),
            ], 422);
        }

        // Conflict check: cannot forbid a perm the user is directly granted
        $directPerms = $user->getDirectPermissions()->pluck('name')->toArray();
        $conflict = array_intersect($forbidList, $directPerms);
        if (! empty($conflict)) {
            return response()->json([
                'message' => 'Mgongano: ruhusa hizi zimepewa moja kwa moja, ziondoe kwanza kabla ya kukataza: '.implode(', ', $conflict),
            ], 422);
        }

        $user->update(['forbidden_permissions' => $forbidList]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLogger::log('user_permissions_restricted', $user, [
            'forbidden_permissions' => $forbidList,
            'restricted_by' => auth()->user()->name,
        ]);

        return response()->json([
            'message' => "Restrictions updated for '{$user->name}'.",
            'user' => $user->load(['roles', 'permissions', 'school']),
            'forbidden_permissions' => $user->forbidden_permissions ?? [],
        ]);
    }

    // ── Guards ────────────────────────────────────────────────────────────────
    private function guardSelf(User $user): void
    {
        if (auth()->id() === $user->id) {
            abort(422, 'You cannot modify your own account through this interface.');
        }
    }

    private function guardSuperadmin(User $user): void
    {
        if ($user->hasRole('superadmin')) {
            abort(422, 'Superadmin accounts cannot be deactivated or deleted through this interface.');
        }
    }
}
