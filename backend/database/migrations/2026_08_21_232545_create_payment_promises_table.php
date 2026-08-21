<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete(); // accountant
            $table->date('promised_date');
            $table->unsignedBigInteger('amount_cents');         // promised amount
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'kept', 'broken'])->default('pending');
            // SMS tracking
            $table->boolean('reminder_sent_day_before')->default(false);
            $table->boolean('reminder_sent_on_day')->default(false);
            $table->timestamps();

            $table->index(['promised_date', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_promises');
    }
};
