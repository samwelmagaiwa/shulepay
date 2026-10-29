<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A new account is activated with a one-time code sent by SMS; only a keyed hash of that code is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_code_hash', 64)->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('activation_sent_at')->nullable();
            $table->unsignedTinyInteger('activation_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_code_hash', 'activation_expires_at', 'activation_sent_at', 'activation_attempts']);
        });
    }
};
