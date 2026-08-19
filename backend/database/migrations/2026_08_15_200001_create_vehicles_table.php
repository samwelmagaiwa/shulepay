<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('plate_number')->unique();
            $table->string('make');
            $table->string('model');
            $table->smallInteger('year');
            $table->integer('capacity');
            $table->enum('type', ['bus', 'minibus', 'van', 'car']);
            $table->string('color')->nullable();
            $table->enum('status', ['active', 'maintenance', 'retired'])->default('active');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
