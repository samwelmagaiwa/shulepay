<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete(); // scoping dimension
            $table->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->enum('method', ['cash', 'mpesa', 'bank'])->default('cash');
            $table->string('reference_number')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'paid_at']);
            $table->index(['student_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
