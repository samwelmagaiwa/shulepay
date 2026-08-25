<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The discounts.type enum was created with Swahili values (ndugu, mwalimu,
     * bursary, nyingine), but every write path in the app (student registration,
     * the manual discount endpoint) has always sent English values (sibling,
     * staff, sponsor, other, general). MySQL silently truncates any value not
     * in the enum, which throws "Data truncated for column 'type'" and blocks
     * discount creation entirely. Widen additively (old values still valid)
     * to avoid breaking any historical rows.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE discounts MODIFY type ".
            "ENUM('ndugu','mwalimu','bursary','nyingine','sibling','staff','sponsor','other','general') ".
            "NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        // Fold the English values back onto their Swahili equivalents before narrowing.
        DB::table('discounts')->where('type', 'sibling')->update(['type' => 'ndugu']);
        DB::table('discounts')->where('type', 'staff')->update(['type' => 'mwalimu']);
        DB::table('discounts')->where('type', 'sponsor')->update(['type' => 'bursary']);
        DB::table('discounts')->whereIn('type', ['other', 'general'])->update(['type' => 'nyingine']);

        DB::statement("ALTER TABLE discounts MODIFY type ".
            "ENUM('ndugu','mwalimu','bursary','nyingine') NOT NULL DEFAULT 'nyingine'");
    }
};
