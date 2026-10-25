<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam module — central (multi-school) database.
 *
 * The exams / exam_results / subjects tables already exist; this adds what the
 * workflow needs (status, publishing, absence, who entered marks) and creates
 * the per-school grading scale. Each change is guarded so the migration is safe
 * to run against a database that already has some of it.
 *
 * The tenant-database equivalent lives in database/migrations/tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'status')) {
                $table->string('status', 20)->default('draft')->after('type');
            }
            if (! Schema::hasColumn('exams', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
            if (! Schema::hasColumn('exams', 'published_by')) {
                $table->unsignedBigInteger('published_by')->nullable();
            }
            if (! Schema::hasColumn('exams', 'grading_snapshot')) {
                // Grading scale frozen at publish so published report cards never shift.
                $table->json('grading_snapshot')->nullable();
            }
            if (! Schema::hasColumn('exams', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
        });

        Schema::table('exam_results', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_results', 'is_absent')) {
                $table->boolean('is_absent')->default(false)->after('marks');
            }
            if (! Schema::hasColumn('exam_results', 'entered_by')) {
                $table->unsignedBigInteger('entered_by')->nullable();
            }
        });

        if (! Schema::hasTable('grading_scales')) {
            Schema::create('grading_scales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->string('grade', 5);
                // Lower bound only: a band runs up to the next band's lower bound,
                // so bands can never overlap or leave a gap.
                $table->decimal('min_marks', 5, 2);
                $table->unsignedTinyInteger('points')->nullable();
                $table->string('remark', 100)->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'grade']);
                $table->unique(['school_id', 'min_marks']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scales');

        Schema::table('exam_results', function (Blueprint $table) {
            foreach (['is_absent', 'entered_by'] as $column) {
                if (Schema::hasColumn('exam_results', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            foreach (['status', 'published_at', 'published_by', 'created_by', 'grading_snapshot'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
