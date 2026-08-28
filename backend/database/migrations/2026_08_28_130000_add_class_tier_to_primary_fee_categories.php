<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defined Primary Fees originally covered Standard 4-6 only. The school also
 * has a separate (lower) fee tier for Standard 1-3, using the same 4 service
 * categories but different amounts — so a school now configures two tiers,
 * not one flat set of 4 categories.
 *
 * Every existing row predates this column and was Standard 4-6 data, so it
 * backfills to 'std_4_6' rather than losing its category identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('primary_fee_categories', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'category']);
            $table->string('class_tier')->default('std_4_6')->after('school_id');
        });

        DB::table('primary_fee_categories')->update(['class_tier' => 'std_4_6']);

        Schema::table('primary_fee_categories', function (Blueprint $table) {
            $table->unique(['school_id', 'class_tier', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('primary_fee_categories', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'class_tier', 'category']);
        });

        // Standard 1-3 rows have no home in the narrower schema — drop them
        // rather than silently merging them into a Standard 4-6 amount.
        DB::table('primary_fee_categories')->where('class_tier', 'std_1_3')->delete();

        Schema::table('primary_fee_categories', function (Blueprint $table) {
            $table->dropColumn('class_tier');
            $table->unique(['school_id', 'category']);
        });
    }
};
