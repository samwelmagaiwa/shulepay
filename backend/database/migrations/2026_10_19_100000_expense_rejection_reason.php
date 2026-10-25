<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expense the owner turns down keeps the reason, so the person who recorded it can see why.
 * (The "rejected" status always existed in the list filter but nothing could set it.) Same file for central and tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'rejection_reason')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'rejection_reason')) {
            Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('rejection_reason'));
        }
    }
};
