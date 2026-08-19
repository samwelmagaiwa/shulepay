<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One enrollment per student per school per academic year.
        // Moving Msingi → Sekondari = new enrollment row, same student identity.
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('admission_number')->unique(); // prefix from school.code: MSG-1042
            $table->enum('status', ['active', 'transferred', 'graduated', 'dropped'])->default('active');
            $table->date('admitted_at');
            $table->timestamps();

            $table->unique(['student_id', 'school_id', 'academic_year_id'], 'enrollment_unique');
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
