<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    // ── All application permissions, grouped by module ────────────────────────
    public static function allPermissions(): array
    {
        return [
            'Dashboard' => ['dashboard.view'],
            'Students' => ['students.view', 'students.create', 'students.edit', 'students.delete', 'students.bulk_import'],
            'Guardians' => ['guardians.view', 'guardians.create', 'guardians.edit', 'guardians.delete'],
            'Invoices' => ['invoices.view', 'invoices.generate'],
            'Payments' => ['payments.view', 'payments.create'],
            'Installments' => ['installments.view', 'installments.create', 'installments.mark_paid'],
            'Refunds' => ['refunds.view', 'refunds.create', 'refunds.delete'],
            'Fee Structures' => ['fee_structures.view', 'fee_structures.create', 'fee_structures.edit', 'fee_structures.delete'],
            'Discounts' => ['discounts.view', 'discounts.create', 'discounts.delete'],
            'Clearance' => ['clearance.view', 'clearance.issue'],
            'Expenses' => ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete', 'expenses.approve'],
            'Petty Cash' => ['petty_cash.view', 'petty_cash.create'],
            'Payroll' => ['payroll.view', 'payroll.create', 'payroll.mark_paid'],
            'Employees' => ['employees.view', 'employees.create', 'employees.edit', 'employees.delete'],
            'Suppliers' => ['suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete'],
            'Assets' => ['assets.view', 'assets.create', 'assets.edit', 'assets.delete', 'assets.dispose'],
            'Budgets' => ['budgets.view', 'budgets.create', 'budgets.edit', 'budgets.delete', 'budgets.activate'],
            'Attendance' => ['attendance.view', 'attendance.mark'],
            'Transport' => ['transport.view', 'transport.manage'],
            'Inventory' => ['inventory.view', 'inventory.manage', 'inventory.adjustment'],
            'Reports' => ['reports.view', 'reports.export'],
            'SMS' => ['sms.send'],
            'Users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
            'Schools' => ['schools.view', 'schools.create', 'schools.edit', 'schools.delete'],
            'Audit Log' => ['audit.view'],
            'Access' => ['multi_school'],
        ];
    }

    // ── Seed all permissions into DB (idempotent) ─────────────────────────────
    private function ensurePermissionsExist(): void
    {
        foreach (self::allPermissions() as $perms) {
            foreach ($perms as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
        }
    }

    // ── GET /superadmin/roles ─────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $this->ensurePermissionsExist();

        $roles = Role::with('permissions')->orderBy('name')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name'),
            ]);

        $allPermissions = self::allPermissions();
        $allFlat = collect($allPermissions)->flatten()->values();

        return response()->json([
            'roles' => $roles,
            'all_permissions' => $allPermissions,
            'all_flat' => $allFlat,
        ]);
    }

    // ── POST /superadmin/roles ────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        AuditLogger::log('role_created', null, [
            'role' => $role->name,
            'created_by' => auth()->user()->name,
        ]);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => [],
        ], 201);
    }

    // ── DELETE /superadmin/roles/{role} ───────────────────────────────────────
    public function destroy(Role $role): JsonResponse
    {
        $protected = ['superadmin', 'owner', 'accountant', 'parent',
            'teacher', 'head_teacher', 'headmaster', 'academic_teacher'];

        if (in_array($role->name, $protected)) {
            return response()->json(['message' => "Role '{$role->name}' is a system role and cannot be deleted."], 422);
        }

        AuditLogger::log('role_deleted', null, [
            'role' => $role->name,
            'deleted_by' => auth()->user()->name,
        ]);

        $role->delete();

        return response()->json(null, 204);
    }

    // ── PUT /superadmin/roles/{role}/permissions ───────────────────────────────
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $this->ensurePermissionsExist();

        // Only sync permissions that actually exist in our defined list
        $valid = collect(self::allPermissions())->flatten()->all();
        $toSync = array_intersect($data['permissions'], $valid);

        $before = $role->permissions->pluck('name')->sort()->values()->toArray();
        $role->syncPermissions($toSync);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $role->fresh()->permissions->pluck('name')->sort()->values()->toArray();
        AuditLogger::log('role_permissions_updated', null, [
            'role' => $role->name,
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
            'updated_by' => auth()->user()->name,
        ]);

        return response()->json([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $after,
        ]);
    }

    // ── GET /superadmin/permissions ───────────────────────────────────────────
    public function permissions(): JsonResponse
    {
        $this->ensurePermissionsExist();

        return response()->json(self::allPermissions());
    }
}
