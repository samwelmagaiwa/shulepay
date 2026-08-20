<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate([
            'name'       => 'inventory.adjustment',
            'guard_name' => 'web',
        ]);

        // Grant to superadmin role by default (idempotent)
        $superadmin = \Spatie\Permission\Models\Role::where('name', 'superadmin')->first();
        if ($superadmin && ! $superadmin->hasPermissionTo($perm)) {
            $superadmin->givePermissionTo($perm);
        }

        // Clear Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'inventory.adjustment')->delete();
    }
};
