<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_entries', 'paye_cents')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                $table->integer('paye_cents')->default(0)->after('deductions_cents');
                $table->integer('nssf_employee_cents')->default(0)->after('paye_cents');
                $table->integer('nssf_employer_cents')->default(0)->after('nssf_employee_cents');
                $table->integer('wcf_cents')->default(0)->after('nssf_employer_cents');
                $table->integer('sdl_cents')->default(0)->after('wcf_cents');
                $table->text('notes')->nullable()->after('sdl_cents');
            });
        }

        if (! Schema::hasTable('payroll_rates')) {
            Schema::create('payroll_rates', function (Blueprint $table) {
                $table->id();
                $table->decimal('nssf_employee_pct', 5, 2)->default(10.00);
                $table->decimal('nssf_employer_pct', 5, 2)->default(10.00);
                $table->decimal('wcf_pct', 5, 2)->default(0.50);
                $table->decimal('sdl_pct', 5, 2)->default(4.50);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_rates');
    }
};
