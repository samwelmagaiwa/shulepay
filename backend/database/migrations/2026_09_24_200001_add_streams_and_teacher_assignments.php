<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Classes can have a stream ("Form 1" + "A"). Tenant databases already had the
 *   column; the central database gains it here.
 * - teacher_assignments says which subject a teacher teaches in which class, so
 *   marks entry can be limited to the teacher's own classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('school_classes', 'stream')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->string('stream', 50)->nullable();
            });

            // A school's "Form 1" may now have several streams, so uniqueness includes the stream.
            // Add a plain index on school_id first so MySQL's FK still has an index backing it
            // after the compound unique is replaced.
            Schema::table('school_classes', function (Blueprint $table) {
                $table->index('school_id', 'school_classes_school_id_idx');
                $table->dropUnique(['school_id', 'name']);
                $table->unique(['school_id', 'name', 'stream']);
            });
        }

        if (! Schema::hasTable('teacher_assignments')) {
            Schema::create('teacher_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'school_class_id', 'subject_id'], 'teacher_assignment_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
