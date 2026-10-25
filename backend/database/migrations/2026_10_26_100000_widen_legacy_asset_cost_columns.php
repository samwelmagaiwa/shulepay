<?php

// The assets table kept its original NOT NULL cost_cents / current_value_cents (32-bit, no default)
// after the register was expanded, so registering an asset failed and a large purchase (above about
// TZS 21 million) could not be stored. Widen them and give them a default; the table is only
// widened, no value changes.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table) {
            if (Schema::hasColumn('assets', 'cost_cents')) {
                $table->unsignedBigInteger('cost_cents')->default(0)->change();
            }
            if (Schema::hasColumn('assets', 'current_value_cents')) {
                $table->unsignedBigInteger('current_value_cents')->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        // Narrowing again could cut a stored value; leave the wider columns in place.
    }
};
