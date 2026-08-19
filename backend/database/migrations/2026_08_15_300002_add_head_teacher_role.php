<?php
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void {
        Role::firstOrCreate(['name' => 'head_teacher', 'guard_name' => 'web']);
    }
    public function down(): void {
        Role::where('name', 'head_teacher')->delete();
    }
};
