<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An exam covers the whole school by default (all_classes = true, no rows in
 * exam_classes). Switching all_classes off restricts it to the classes listed in
 * exam_classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('exams', 'all_classes')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->boolean('all_classes')->default(true);
            });
        }

        if (! Schema::hasTable('exam_classes')) {
            Schema::create('exam_classes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->unique(['exam_id', 'school_class_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_classes');

        if (Schema::hasColumn('exams', 'all_classes')) {
            Schema::table('exams', fn (Blueprint $table) => $table->dropColumn('all_classes'));
        }
    }
};
