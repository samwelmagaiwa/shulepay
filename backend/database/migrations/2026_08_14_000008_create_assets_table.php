<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->enum('category', ['building', 'equipment', 'vehicle', 'furniture', 'other']);
            $table->integer('cost_cents');
            $table->integer('current_value_cents');
            $table->date('purchase_date');
            $table->enum('condition', ['good', 'fair', 'poor', 'disposed'])->default('good');
            $table->string('serial_number')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('assets');
    }
};
