<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->integer('basic_salary_cents');
            $table->integer('allowances_cents')->default(0);
            $table->integer('deductions_cents')->default(0);
            $table->integer('net_salary_cents');
            $table->enum('payment_method', ['cash', 'bank', 'mpesa']);
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
            $table->unique(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
