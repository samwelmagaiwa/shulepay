<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->enum('type', ['top_up', 'expense']);
            $table->integer('amount_cents');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->integer('balance_after_cents');
            $table->foreignId('recorded_by')->constrained('users');
            $table->date('entry_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_entries');
    }
};
