<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user privacy lock for the dashboard's money figures.
 *
 * One row per user: each superadmin, owner and accountant sets their own code,
 * and unlocking for one never reveals the figures for another. Only the hash is
 * stored, so a leaked database row does not yield the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_locks');
    }
};
