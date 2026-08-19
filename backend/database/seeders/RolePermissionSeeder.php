<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'superadmin',
            'owner',
            'accountant',
            'parent',
            'teacher_pri',   // Class Teacher — primary schools only
            'teacher_sec',   // Subject Teacher — secondary schools only
            'academic_pri',  // Academic Teacher — primary schools only
            'academic_sec',  // Academic Teacher — secondary schools only
            'head_teacher',  // Head Teacher — primary schools only
            'headmaster',    // Headmaster/Headmistress — secondary schools only
        ];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
