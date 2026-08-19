<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('student_id')
                ->constrained()->nullOnDelete();
            $table->string('method', 20)->default('cash')->after('reason');
            $table->unsignedBigInteger('payment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'method']);
            $table->unsignedBigInteger('payment_id')->nullable(false)->change();
        });
    }
};
