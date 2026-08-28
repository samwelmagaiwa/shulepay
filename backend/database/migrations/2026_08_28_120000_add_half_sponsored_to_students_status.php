<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Half-sponsored students were previously forced into the generic 'sponsored'
 * status (shared with fully-sponsored students), which made the two levels
 * indistinguishable in the status column. This adds a dedicated
 * 'half_sponsored' value so Student::effectiveStatus() can tell them apart.
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
            'ALTER TABLE `students` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped','sponsored','orphaned','half_sponsored') ".
            "NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // The narrower enum cannot hold 'half_sponsored', so fold it back to
        // 'sponsored' before shrinking — otherwise MySQL would truncate those
        // rows to an empty string.
        DB::table('students')->where('status', 'half_sponsored')->update(['status' => 'sponsored']);

        DB::statement(
            'ALTER TABLE `students` MODIFY `status` '.
            "ENUM('active','transferred','graduated','dropped','sponsored','orphaned') ".
            "NOT NULL DEFAULT 'active'"
        );
    }
};
