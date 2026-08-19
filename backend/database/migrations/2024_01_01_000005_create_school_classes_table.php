<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Darasa la 1", "Kidato cha 1"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
