<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * students.status was enum('active','transferred','graduated','dropped'), but both the
 * registration form and RegisterStudentRequest already offer and accept 'sponsored' and
 * 'orphaned'. Selecting either produced a "Data truncated for column 'status'" failure on
 * save. This widens the enum so the statuses the application already exposes are storable.
 *
 * Widening an enum is additive: every existing row keeps its current value.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ALTER ... MODIFY is MySQL-only. SQLite (used by the test suite) has no native
        // ENUM type, so there is no column to widen there.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `students` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped','sponsored','orphaned') ".
            "NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // The narrower enum cannot hold the two new values, so fold them back to 'active'
        // before shrinking — otherwise MySQL would truncate those rows to an empty string.
        DB::table('students')->whereIn('status', ['sponsored', 'orphaned'])->update(['status' => 'active']);

        DB::statement(
            'ALTER TABLE `students` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped') ".
            "NOT NULL DEFAULT 'active'"
        );
    }
};
