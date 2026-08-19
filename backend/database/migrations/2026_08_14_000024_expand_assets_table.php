<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Identity
            if (!Schema::hasColumn('assets', 'asset_tag')) {
                $table->string('asset_tag')->unique()->after('id');
            }
            if (!Schema::hasColumn('assets', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('asset_tag');
            }
            if (!Schema::hasColumn('assets', 'serial_no')) {
                $table->string('serial_no')->nullable();
            }
            if (!Schema::hasColumn('assets', 'photo')) {
                $table->string('photo')->nullable();
            }

            // Purchase
            if (!Schema::hasColumn('assets', 'purchase_cost_cents')) {
                $table->unsignedBigInteger('purchase_cost_cents')->default(0);
            }
            if (!Schema::hasColumn('assets', 'supplier_name')) {
                $table->string('supplier_name')->nullable();
            }
            if (!Schema::hasColumn('assets', 'invoice_no')) {
                $table->string('invoice_no')->nullable();
            }
            if (!Schema::hasColumn('assets', 'funding_source')) {
                $table->string('funding_source')->nullable();
            }

            // Depreciation
            if (!Schema::hasColumn('assets', 'depreciation_method')) {
                $table->string('depreciation_method')->nullable();
            }
            if (!Schema::hasColumn('assets', 'useful_life_years')) {
                $table->unsignedSmallInteger('useful_life_years')->nullable();
            }
            if (!Schema::hasColumn('assets', 'depreciation_rate')) {
                $table->decimal('depreciation_rate', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('assets', 'salvage_value_cents')) {
                $table->unsignedBigInteger('salvage_value_cents')->default(0);
            }
            if (!Schema::hasColumn('assets', 'accumulated_depreciation_cents')) {
                $table->unsignedBigInteger('accumulated_depreciation_cents')->default(0);
            }

            // Custody
            if (!Schema::hasColumn('assets', 'custodian')) {
                $table->string('custodian')->nullable();
            }
            if (!Schema::hasColumn('assets', 'warranty_expiry')) {
                $table->date('warranty_expiry')->nullable();
            }

            // Status
            if (!Schema::hasColumn('assets', 'status')) {
                $table->string('status')->default('in_use');
            }

            // Disposal
            if (!Schema::hasColumn('assets', 'disposal_date')) {
                $table->date('disposal_date')->nullable();
            }
            if (!Schema::hasColumn('assets', 'disposal_value_cents')) {
                $table->unsignedBigInteger('disposal_value_cents')->default(0);
            }
            if (!Schema::hasColumn('assets', 'disposal_reason')) {
                $table->text('disposal_reason')->nullable();
            }

            // Audit
            if (!Schema::hasColumn('assets', 'registered_by')) {
                $table->unsignedBigInteger('registered_by')->nullable();
                $table->foreign('registered_by')->references('id')->on('users');
            }
            if (!Schema::hasColumn('assets', 'registered_at')) {
                $table->timestamp('registered_at')->nullable();
            }
        });

        // Make purchase_date nullable (was NOT NULL)
        Schema::table('assets', function (Blueprint $table) {
            $table->date('purchase_date')->nullable()->change();
        });

        // Change category to string to accommodate expanded values
        Schema::table('assets', function (Blueprint $table) {
            $table->string('category')->default('other')->change();
        });

        // Change condition to string to accommodate expanded values (excellent/good/fair/poor)
        Schema::table('assets', function (Blueprint $table) {
            $table->string('condition')->default('good')->change();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $columns = [
                'asset_tag', 'quantity', 'serial_no', 'photo',
                'purchase_cost_cents', 'supplier_name', 'invoice_no', 'funding_source',
                'depreciation_method', 'useful_life_years', 'depreciation_rate',
                'salvage_value_cents', 'accumulated_depreciation_cents',
                'custodian', 'warranty_expiry', 'status',
                'disposal_date', 'disposal_value_cents', 'disposal_reason',
                'registered_at',
            ];

            // Drop foreign key first
            if (Schema::hasColumn('assets', 'registered_by')) {
                $table->dropForeign(['registered_by']);
                $table->dropColumn('registered_by');
            }

            foreach ($columns as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
