<?php

// Discount::$fillable and DiscountController::store both write `applied_at`, but no
// migration ever created the column — recording any discount returned a 500 centrally too.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discounts') && ! Schema::hasColumn('discounts', 'applied_at')) {
            Schema::table('discounts', function (Blueprint $table) {
                $table->timestamp('applied_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Additive — nothing to reverse.
    }
};
