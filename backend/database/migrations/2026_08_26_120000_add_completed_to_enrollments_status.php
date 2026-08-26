<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Year-rollover (RolloverController::execute) marks a student's old enrollment
 * 'completed' once a new enrollment exists for the following academic year —
 * distinct from 'transferred'/'graduated'/'dropped', which describe why a
 * student left the school entirely, not that they simply moved up a year.
 * The enum never had this value, so every promoted/repeated rollover threw
 * "Data truncated for column 'status'" the moment it tried to close out the
 * old enrollment.
 *
 * Widening an enum is additive: every existing row keeps its current value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `enrollments` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped','completed') ".
            "NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('enrollments')->where('status', 'completed')->update(['status' => 'transferred']);

        DB::statement(
            'ALTER TABLE `enrollments` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped') NOT NULL DEFAULT 'active'"
        );
    }
};
