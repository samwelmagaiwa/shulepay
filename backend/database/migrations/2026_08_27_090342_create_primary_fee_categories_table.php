<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('primary_fee_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // hostel | day_transport_food | day_food_only | day_none
            $table->string('category');
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primary_fee_categories');
    }
};
