<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. Masomo, Usafiri, Bweni, Sare, Chakula, Mtihani
            $table->enum('category', ['masomo', 'usafiri', 'bweni', 'sare', 'chakula', 'mtihani', 'nyingine'])->default('nyingine');
            $table->unsignedBigInteger('amount_cents'); // stored as integer minor units
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_items');
    }
};
