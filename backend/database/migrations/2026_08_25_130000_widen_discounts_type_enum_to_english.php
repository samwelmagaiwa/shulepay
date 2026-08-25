<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The discounts.type enum was created with Swahili values (ndugu, mwalimu, bursary,
 * nyingine), but every write path in the app (student registration, the manual discount
 * endpoint) has always sent English values (sibling, staff, sponsor, other, general).
 * MySQL silently truncated any value not in the enum, throwing "Data truncated for
 * column 'type'" and blocking discount creation entirely.
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
            'ALTER TABLE `discounts` MODIFY `type` '.
            "ENUM('ndugu','mwalimu','bursary','nyingine','sibling','staff','sponsor','other','general') ".
            "NOT NULL DEFAULT 'other'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Fold the English values back onto their Swahili equivalents before narrowing.
        DB::table('discounts')->where('type', 'sibling')->update(['type' => 'ndugu']);
        DB::table('discounts')->where('type', 'staff')->update(['type' => 'mwalimu']);
        DB::table('discounts')->where('type', 'sponsor')->update(['type' => 'bursary']);
        DB::table('discounts')->whereIn('type', ['other', 'general'])->update(['type' => 'nyingine']);

        DB::statement(
            'ALTER TABLE `discounts` MODIFY `type` '.
            "ENUM('ndugu','mwalimu','bursary','nyingine') NOT NULL DEFAULT 'nyingine'"
        );
    }
};
