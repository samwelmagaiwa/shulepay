<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A locked dashboard previously had only two states: no code configured, or
 * configured-and-always-enforced. A user who wants to stop being asked for
 * the code — without losing the code itself, so it can be turned back on
 * later — had no way to do that short of deleting the lock (destroy()) and
 * setting a brand-new code from scratch.
 *
 * is_enabled defaults true so every existing lock keeps enforcing exactly as
 * before; only a deliberate "deactivate" call ever sets it false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_locks', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_locks', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
