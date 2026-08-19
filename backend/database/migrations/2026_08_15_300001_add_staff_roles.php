<?php
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void {
        $roles = [
            'teacher',          // class teacher — primary & secondary
            'headmaster',       // head teacher — secondary schools
            'academic_teacher', // academic/subject teacher — primary & secondary
        ];
        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function down(): void {
        Role::whereIn('name', ['teacher', 'headmaster', 'academic_teacher'])->delete();
    }
};
