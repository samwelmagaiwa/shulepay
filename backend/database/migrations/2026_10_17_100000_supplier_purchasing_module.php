<?php

// Supplier purchasing: bills, allocated payments with approval, goods-received notes,
// documents, categories and the links from expenses / assets / stock to a supplier.
//
// The SAME file lives in database/migrations (central) and database/migrations/tenant:
// a central table carries school_id, a tenant table does not, so the shape is chosen by
// whether `suppliers` has that column. Purely additive — nothing is dropped, and the
// legacy balance is carried into `opening_balance_cents` so the ledger reproduces it.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function add(string $table, string $column, callable $define): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $t) => $define($t));
        }
    }

    /** The school column, only where several schools share the table. */
    private function school(Blueprint $t, bool $central): void
    {
        if ($central) {
            $t->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
        }
    }

    public function up(): void
    {
        $central = Schema::hasColumn('suppliers', 'school_id');

        // ── suppliers ─────────────────────────────────────────────────────────
        $this->add('suppliers', 'code', fn ($t) => $t->string('code', 30)->nullable());
        $this->add('suppliers', 'supplier_category_id', fn ($t) => $t->unsignedBigInteger('supplier_category_id')->nullable());
        $this->add('suppliers', 'status', fn ($t) => $t->string('status', 20)->default('active'));
        $this->add('suppliers', 'tin', fn ($t) => $t->string('tin', 30)->nullable());
        $this->add('suppliers', 'vrn', fn ($t) => $t->string('vrn', 30)->nullable());
        $this->add('suppliers', 'business_reg_no', fn ($t) => $t->string('business_reg_no', 60)->nullable());
        $this->add('suppliers', 'contact_title', fn ($t) => $t->string('contact_title', 100)->nullable());
        $this->add('suppliers', 'alt_phone', fn ($t) => $t->string('alt_phone', 20)->nullable());
        $this->add('suppliers', 'region', fn ($t) => $t->string('region', 100)->nullable());
        $this->add('suppliers', 'district', fn ($t) => $t->string('district', 100)->nullable());
        $this->add('suppliers', 'bank_name', fn ($t) => $t->string('bank_name', 100)->nullable());
        $this->add('suppliers', 'bank_account_name', fn ($t) => $t->string('bank_account_name', 150)->nullable());
        $this->add('suppliers', 'bank_account_number', fn ($t) => $t->string('bank_account_number', 50)->nullable());
        $this->add('suppliers', 'mobile_money_provider', fn ($t) => $t->string('mobile_money_provider', 30)->nullable());
        $this->add('suppliers', 'mobile_money_number', fn ($t) => $t->string('mobile_money_number', 20)->nullable());
        $this->add('suppliers', 'mobile_money_name', fn ($t) => $t->string('mobile_money_name', 150)->nullable());
        $this->add('suppliers', 'preferred_method', fn ($t) => $t->string('preferred_method', 20)->nullable());
        $this->add('suppliers', 'payment_terms_days', fn ($t) => $t->unsignedSmallInteger('payment_terms_days')->default(0));
        $this->add('suppliers', 'credit_limit_cents', fn ($t) => $t->bigInteger('credit_limit_cents')->default(0));
        $this->add('suppliers', 'opening_balance_cents', fn ($t) => $t->bigInteger('opening_balance_cents')->default(0));
        $this->add('suppliers', 'vat_registered', fn ($t) => $t->boolean('vat_registered')->default(false));
        $this->add('suppliers', 'wht_applicable', fn ($t) => $t->boolean('wht_applicable')->default(false));
        $this->add('suppliers', 'wht_type', fn ($t) => $t->string('wht_type', 10)->nullable());
        $this->add('suppliers', 'rating', fn ($t) => $t->unsignedTinyInteger('rating')->nullable());
        $this->add('suppliers', 'notes', fn ($t) => $t->text('notes')->nullable());
        $this->add('suppliers', 'deactivated_at', fn ($t) => $t->timestamp('deactivated_at')->nullable());

        // A bill posts to expenses when approved, so it must hold the same size of amount.
        Schema::table('expenses', fn (Blueprint $t) => $t->bigInteger('amount_cents')->change());

        if ($central) {
            // 32-bit cents stop at TZS 21 million — too small for a school's supplier ledger.
            Schema::table('suppliers', fn (Blueprint $t) => $t->bigInteger('balance_cents')->default(0)->change());
            Schema::table('supplier_payments', fn (Blueprint $t) => $t->bigInteger('amount_cents')->change());
            // MySQL / MariaDB only: turns the old enum into plain text so new methods can be added. Other drivers
            // (the SQLite the tests use) keep the column as it is, which accepts any text.
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `supplier_payments` MODIFY `method` VARCHAR(20) NOT NULL DEFAULT 'cash'");
            }
            Schema::table('suppliers', function (Blueprint $t) {
                $t->unique(['school_id', 'code'], 'suppliers_school_code_unique');
            });
        } else {
            Schema::table('suppliers', fn (Blueprint $t) => $t->unique('code', 'suppliers_code_unique'));
        }

        // Carry what is already owed into the opening balance, so
        // opening + approved bills − approved payments equals today's balance.
        DB::table('suppliers')->where('opening_balance_cents', 0)->update([
            'opening_balance_cents' => DB::raw('balance_cents + COALESCE((SELECT SUM(p.amount_cents) FROM supplier_payments p WHERE p.supplier_id = suppliers.id), 0)'),
        ]);

        // ── supplier_payments: approval, withholding tax, void ────────────────
        $this->add('supplier_payments', 'status', fn ($t) => $t->string('status', 20)->default('approved'));
        $this->add('supplier_payments', 'wht_cents', fn ($t) => $t->bigInteger('wht_cents')->default(0));
        $this->add('supplier_payments', 'efd_number', fn ($t) => $t->string('efd_number', 60)->nullable());
        $this->add('supplier_payments', 'approved_by', fn ($t) => $t->unsignedBigInteger('approved_by')->nullable());
        $this->add('supplier_payments', 'approved_at', fn ($t) => $t->timestamp('approved_at')->nullable());
        $this->add('supplier_payments', 'voided_by', fn ($t) => $t->unsignedBigInteger('voided_by')->nullable());
        $this->add('supplier_payments', 'voided_at', fn ($t) => $t->timestamp('voided_at')->nullable());
        $this->add('supplier_payments', 'void_reason', fn ($t) => $t->string('void_reason', 255)->nullable());

        // ── new tables ────────────────────────────────────────────────────────
        if (! Schema::hasTable('supplier_categories')) {
            Schema::create('supplier_categories', function (Blueprint $t) use ($central) {
                $t->id();
                $this->school($t, $central);
                $t->string('key', 40)->nullable();
                $t->string('name', 100)->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_bills')) {
            Schema::create('supplier_bills', function (Blueprint $t) use ($central) {
                $t->id();
                $this->school($t, $central);
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->string('bill_number', 60);
                $t->date('bill_date');
                $t->date('due_date')->nullable();
                $t->bigInteger('subtotal_cents')->default(0);
                $t->bigInteger('vat_cents')->default(0);
                $t->bigInteger('total_cents')->default(0);
                $t->bigInteger('paid_cents')->default(0);
                $t->string('status', 20)->default('pending_approval');
                $t->unsignedBigInteger('expense_category_id')->nullable();
                $t->unsignedBigInteger('expense_id')->nullable();
                $t->unsignedBigInteger('academic_year_id')->nullable();
                $t->unsignedBigInteger('term_id')->nullable();
                $t->string('attachment_path')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->unsignedBigInteger('cancelled_by')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->string('cancel_reason', 255)->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->unique(['supplier_id', 'bill_number'], 'supplier_bills_supplier_number_unique');
                $t->index(['status', 'due_date']);
            });
        }

        if (! Schema::hasTable('supplier_bill_items')) {
            Schema::create('supplier_bill_items', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
                $t->string('description');
                $t->decimal('quantity', 12, 2)->default(1);
                $t->string('unit', 30)->nullable();
                $t->bigInteger('unit_price_cents')->default(0);
                $t->bigInteger('line_total_cents')->default(0);
                $t->unsignedBigInteger('inventory_item_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_payment_allocations')) {
            Schema::create('supplier_payment_allocations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
                $t->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
                $t->bigInteger('amount_cents');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_documents')) {
            Schema::create('supplier_documents', function (Blueprint $t) use ($central) {
                $t->id();
                $this->school($t, $central);
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->string('type', 40);
                $t->string('title', 150)->nullable();
                $t->string('reference_number', 80)->nullable();
                $t->date('issue_date')->nullable();
                $t->date('expiry_date')->nullable();
                $t->string('file_path')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedTinyInteger('last_alert_stage')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $t) use ($central) {
                $t->id();
                $this->school($t, $central);
                $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $t->string('grn_number', 40);
                $t->date('receipt_date');
                $t->string('delivery_note_no', 60)->nullable();
                $t->unsignedBigInteger('supplier_bill_id')->nullable();
                $t->string('status', 20)->default('received');
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->unsignedBigInteger('voided_by')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->string('void_reason', 255)->nullable();
                $t->timestamps();
                if ($central) {
                    $t->unique(['school_id', 'grn_number'], 'goods_receipts_school_grn_unique');
                } else {
                    $t->unique('grn_number', 'goods_receipts_grn_unique');
                }
            });
        }

        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $t) {
                $t->id();
                $t->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $t->unsignedBigInteger('inventory_item_id');
                $t->decimal('quantity', 10, 2);
                $t->bigInteger('unit_cost_cents')->default(0);
                $t->string('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── links from the rest of the school's records to a supplier ─────────
        $this->add('expenses', 'supplier_id', fn ($t) => $t->unsignedBigInteger('supplier_id')->nullable());
        $this->add('expenses', 'supplier_bill_id', fn ($t) => $t->unsignedBigInteger('supplier_bill_id')->nullable());
        // 'credit' = incurred on a supplier bill; settled later by supplier payments, so cash-flow
        // must not count it a second time.
        $this->add('expenses', 'payment_mode', fn ($t) => $t->string('payment_mode', 10)->default('paid'));
        $this->add('assets', 'supplier_id', fn ($t) => $t->unsignedBigInteger('supplier_id')->nullable());
        $this->add('inventory_items', 'supplier_id', fn ($t) => $t->unsignedBigInteger('supplier_id')->nullable());
        $this->add('inventory_transactions', 'supplier_id', fn ($t) => $t->unsignedBigInteger('supplier_id')->nullable());
        $this->add('inventory_transactions', 'delivery_note_no', fn ($t) => $t->string('delivery_note_no', 60)->nullable());
        $this->add('inventory_transactions', 'goods_receipt_id', fn ($t) => $t->unsignedBigInteger('goods_receipt_id')->nullable());
    }

    public function down(): void
    {
        // Additive, data-bearing migration — nothing to reverse.
    }
};
