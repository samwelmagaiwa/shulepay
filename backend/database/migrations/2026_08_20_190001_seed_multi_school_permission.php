<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'multi_school', 'guard_name' => 'web']);

        // Auto-grant to superadmin and owner roles so they can manage school access
        foreach (['superadmin', 'owner'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo('multi_school')) {
                $role->givePermissionTo($perm);
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'multi_school')->delete();
    }
};
