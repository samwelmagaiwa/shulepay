<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->enum('fee_mode', ['per_term', 'full_tuition'])->default('per_term')->after('is_active');
            $table->unsignedBigInteger('full_tuition_cents')->nullable()->after('fee_mode');
            $table->unsignedTinyInteger('installments_count')->nullable()->after('full_tuition_cents');
            $table->unsignedTinyInteger('installment_number')->nullable()->after('installments_count');
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn(['fee_mode', 'full_tuition_cents', 'installments_count', 'installment_number']);
        });
    }
};
