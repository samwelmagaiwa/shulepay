<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsorship level is a separate dimension from `status` (active/transferred/
 * graduated/dropped/...) — a sponsored student can still be transferred, or
 * graduate, independently of their sponsorship. Kept as its own column rather
 * than folded into `status`, which would make a transferred-and-sponsored
 * student unrepresentable (see 2026_08_24_150000 for the enum-widening pain
 * that came from conflating status values before).
 *
 * 'full'  — sponsor covers everything; no tuition fee, no invoice generated.
 * 'half'  — a reporting/tracking label only; billing behaves exactly as for a
 *           non-sponsored student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('sponsorship_type', ['none', 'half', 'full'])
                ->default('none')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('sponsorship_type');
        });
    }
};
