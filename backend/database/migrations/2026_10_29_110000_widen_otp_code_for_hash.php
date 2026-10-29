<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One-time codes are now stored as a keyed hash instead of in clear. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('code', 64)->change();
        });
        // codes that were stored in clear are short-lived (10 minutes); retire any still open
        DB::table('otp_codes')->whereNull('used_at')->update(['used_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('code', 6)->change();
        });
    }
};
