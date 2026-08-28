<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            // Phone was previously NOT NULL UNIQUE.
            // Guardian phone is now optional, so we make it nullable.
            // The unique constraint is kept so that two guardians cannot
            // share the same number, but multiple phone-less guardians
            // are allowed (MySQL treats each NULL as distinct).
            $table->string('phone', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
