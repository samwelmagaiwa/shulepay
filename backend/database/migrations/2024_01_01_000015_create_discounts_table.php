<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['ndugu', 'mwalimu', 'bursary', 'nyingine'])->default('nyingine');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->decimal('percentage', 5, 2)->unsigned()->default(0); // 0.00–100.00
            $table->boolean('is_percentage')->default(false);
            $table->string('reason')->nullable();
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
