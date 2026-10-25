<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A budget line can follow real spending: it points to an expense category (its actual is then the approved
 * expenses of that category in the budget's year) or is the "refunds" line (its actual is the refunds paid out).
 * A line with neither keeps the old hand-typed actual. Same file for central and tenant databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            if (! Schema::hasColumn('budget_items', 'expense_category_id')) {
                $table->unsignedBigInteger('expense_category_id')->nullable();
            }
            if (! Schema::hasColumn('budget_items', 'kind')) {
                $table->string('kind', 20)->default('expense');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_items', function (Blueprint $table) {
            foreach (['expense_category_id', 'kind'] as $col) {
                if (Schema::hasColumn('budget_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
