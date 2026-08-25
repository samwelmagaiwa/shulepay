<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits full sponsorship into two cases.
 *
 *   full       fully sponsored, student makes no payments at all  (existing rows)
 *   full_paid  fully sponsored, but payments are still recorded against a fee
 *
 * Purely additive: 'full' keeps its current meaning, so no existing row changes
 * and no data migration is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ALTER ... MODIFY is MySQL-only; SQLite (test suite) has no ENUM type.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE `students` MODIFY `sponsorship_type` '.
            "ENUM('none','half','full','full_paid') NOT NULL DEFAULT 'none'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Fold the new value back before narrowing, otherwise MySQL truncates
        // those rows to an empty string.
        DB::table('students')->where('sponsorship_type', 'full_paid')
            ->update(['sponsorship_type' => 'full']);

        DB::statement(
            'ALTER TABLE `students` MODIFY `sponsorship_type` '.
            "ENUM('none','half','full') NOT NULL DEFAULT 'none'"
        );
    }
};
