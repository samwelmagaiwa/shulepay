<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add columns to inventory_items (idempotent) ───────────────────────
        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'type')) {
                $table->string('type', 20)->default('consumable')->after('id');
            }
            // Make 'unit' nullable — fixed assets don't have a unit
            $table->string('unit')->nullable()->change();

            if (! Schema::hasColumn('inventory_items', 'asset_tag')) {
                $table->string('asset_tag', 30)->nullable()->after('type');
            }
            if (! Schema::hasColumn('inventory_items', 'serial_no')) {
                $table->string('serial_no')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'photo')) {
                $table->string('photo')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'category')) {
                $table->string('category')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'condition')) {
                $table->string('condition')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'status')) {
                $table->string('status')->nullable();
            }

            // Purchase
            if (! Schema::hasColumn('inventory_items', 'purchase_cost_cents')) {
                $table->unsignedBigInteger('purchase_cost_cents')->default(0);
            }
            if (! Schema::hasColumn('inventory_items', 'purchase_date')) {
                $table->date('purchase_date')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'supplier_name')) {
                $table->string('supplier_name')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'invoice_no')) {
                $table->string('invoice_no')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'funding_source')) {
                $table->string('funding_source')->nullable();
            }

            // Depreciation
            if (! Schema::hasColumn('inventory_items', 'depreciation_method')) {
                $table->string('depreciation_method')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'useful_life_years')) {
                $table->unsignedSmallInteger('useful_life_years')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'depreciation_rate')) {
                $table->decimal('depreciation_rate', 5, 2)->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'salvage_value_cents')) {
                $table->unsignedBigInteger('salvage_value_cents')->default(0);
            }
            if (! Schema::hasColumn('inventory_items', 'accumulated_depreciation_cents')) {
                $table->unsignedBigInteger('accumulated_depreciation_cents')->default(0);
            }
            if (! Schema::hasColumn('inventory_items', 'current_value_cents')) {
                $table->unsignedBigInteger('current_value_cents')->default(0);
            }

            // Custody
            if (! Schema::hasColumn('inventory_items', 'custodian')) {
                $table->string('custodian')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'warranty_expiry')) {
                $table->date('warranty_expiry')->nullable();
            }

            // Disposal
            if (! Schema::hasColumn('inventory_items', 'disposal_date')) {
                $table->date('disposal_date')->nullable();
            }
            if (! Schema::hasColumn('inventory_items', 'disposal_value_cents')) {
                $table->unsignedBigInteger('disposal_value_cents')->default(0);
            }
            if (! Schema::hasColumn('inventory_items', 'disposal_reason')) {
                $table->text('disposal_reason')->nullable();
            }

            // Notes
            if (! Schema::hasColumn('inventory_items', 'notes')) {
                $table->text('notes')->nullable();
            }

            // Audit
            if (! Schema::hasColumn('inventory_items', 'registered_by')) {
                $table->unsignedBigInteger('registered_by')->nullable();
                $table->foreign('registered_by')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_items', 'registered_at')) {
                $table->timestamp('registered_at')->nullable();
            }
        });

        // ── 2. Migrate existing assets → inventory_items (type = fixed_asset) ────
        $assets = DB::table('assets')->get();
        foreach ($assets as $asset) {
            // Skip if already migrated
            if (
                $asset->asset_tag &&
                DB::table('inventory_items')
                    ->where('asset_tag', $asset->asset_tag)
                    ->where('type', 'fixed_asset')
                    ->exists()
            ) {
                continue;
            }

            DB::table('inventory_items')->insert([
                'type'                           => 'fixed_asset',
                'school_id'                      => $asset->school_id,
                'name'                           => $asset->name,
                'asset_tag'                      => $asset->asset_tag ?? null,
                'serial_no'                      => $asset->serial_no ?? $asset->serial_number ?? null,
                'photo'                          => $asset->photo ?? null,
                'category'                       => $asset->category,
                'condition'                      => $asset->condition,
                'status'                         => $asset->status ?? 'in_use',
                'quantity'                       => $asset->quantity ?? 1,
                'location'                       => $asset->location ?? null,
                'purchase_cost_cents'            => $asset->purchase_cost_cents ?? $asset->cost_cents ?? 0,
                'purchase_date'                  => $asset->purchase_date ?? null,
                'supplier_name'                  => $asset->supplier_name ?? null,
                'invoice_no'                     => $asset->invoice_no ?? null,
                'funding_source'                 => $asset->funding_source ?? null,
                'depreciation_method'            => $asset->depreciation_method ?? null,
                'useful_life_years'              => $asset->useful_life_years ?? null,
                'depreciation_rate'              => $asset->depreciation_rate ?? null,
                'salvage_value_cents'            => $asset->salvage_value_cents ?? 0,
                'accumulated_depreciation_cents' => $asset->accumulated_depreciation_cents ?? 0,
                'current_value_cents'            => $asset->current_value_cents ?? 0,
                'custodian'                      => $asset->custodian ?? null,
                'warranty_expiry'                => $asset->warranty_expiry ?? null,
                'disposal_date'                  => $asset->disposal_date ?? null,
                'disposal_value_cents'           => $asset->disposal_value_cents ?? 0,
                'disposal_reason'                => $asset->disposal_reason ?? null,
                'registered_by'                  => $asset->registered_by ?? null,
                'registered_at'                  => $asset->registered_at ?? null,
                'notes'                          => $asset->notes ?? null,
                'is_active'                      => true,
                'unit'                           => null,
                'unit_cost_cents'                => 0,
                'reorder_level'                  => 0,
                'created_at'                     => $asset->created_at,
                'updated_at'                     => $asset->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_items')->where('type', 'fixed_asset')->delete();

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'registered_by')) {
                $table->dropForeign(['registered_by']);
                $table->dropColumn('registered_by');
            }
            $cols = [
                'type', 'asset_tag', 'serial_no', 'photo', 'category', 'condition', 'status',
                'purchase_cost_cents', 'purchase_date', 'supplier_name', 'invoice_no', 'funding_source',
                'depreciation_method', 'useful_life_years', 'depreciation_rate',
                'salvage_value_cents', 'accumulated_depreciation_cents', 'current_value_cents',
                'custodian', 'warranty_expiry',
                'disposal_date', 'disposal_value_cents', 'disposal_reason',
                'notes', 'registered_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('inventory_items', $col)) {
                    $table->dropColumn($col);
                }
            }
            $table->string('unit')->nullable(false)->change();
        });
    }
};
