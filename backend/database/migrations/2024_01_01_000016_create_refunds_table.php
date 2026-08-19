<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reason');
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('refunded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
