<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('students', 'birth_certificate_no')) {
                $table->string('birth_certificate_no', 50)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('students', 'nationality')) {
                $table->string('nationality', 50)->default('Tanzanian')->after('birth_certificate_no');
            }
            if (! Schema::hasColumn('students', 'photo')) {
                $table->string('photo')->nullable()->after('nationality');
            }
        });

        // Check driver to support SQLite testing environments
        if (DB::getDriverName() === 'mysql') {
            // Step 1: Expand enum to accept both old and new values
            DB::statement("ALTER TABLE students MODIFY gender ENUM('me','ke','male','female') NULL");
            // Step 2: Migrate existing data
            DB::statement("UPDATE students SET gender = 'male' WHERE gender = 'me'");
            DB::statement("UPDATE students SET gender = 'female' WHERE gender = 'ke'");
            // Step 3: Restrict enum to new values only
            DB::statement("ALTER TABLE students MODIFY gender ENUM('male','female') NULL");
        } else {
            DB::statement("UPDATE students SET gender = 'male' WHERE gender = 'me'");
            DB::statement("UPDATE students SET gender = 'female' WHERE gender = 'ke'");
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'birth_certificate_no', 'nationality', 'photo']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE students MODIFY gender ENUM('me','ke') NULL");
        }
    }
};
