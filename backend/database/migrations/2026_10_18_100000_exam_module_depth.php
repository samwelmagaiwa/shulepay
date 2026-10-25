<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam module depth: marks out of a chosen total, a marks deadline, the date the next term opens,
 * an optional fee hold on results, report-card comments, teachers' "submit marks" sign-off and an
 * audit trail of every mark change. The same file is used for the central and the tenant databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'out_of')) {
                $table->unsignedSmallInteger('out_of')->default(100);
            }
            if (! Schema::hasColumn('exams', 'marks_deadline')) {
                $table->date('marks_deadline')->nullable();
            }
            if (! Schema::hasColumn('exams', 'next_term_opens')) {
                $table->date('next_term_opens')->nullable();
            }
            if (! Schema::hasColumn('exams', 'hold_for_fees')) {
                $table->boolean('hold_for_fees')->default(false);
            }
        });

        if (! Schema::hasTable('exam_comments')) {
            Schema::create('exam_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('student_id');
                $table->text('teacher_comment')->nullable();
                $table->text('head_comment')->nullable();
                $table->unsignedBigInteger('entered_by')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'student_id']);
            });
        }

        if (! Schema::hasTable('exam_submissions')) {
            Schema::create('exam_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('school_class_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'school_class_id', 'subject_id'], 'exam_submission_unique');
            });
        }

        if (! Schema::hasTable('exam_mark_changes')) {
            Schema::create('exam_mark_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('subject_id');
                $table->decimal('old_marks', 6, 2)->nullable();
                $table->decimal('new_marks', 6, 2)->nullable();
                $table->boolean('old_absent')->default(false);
                $table->boolean('new_absent')->default(false);
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->string('changed_by_name', 120)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['exam_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_mark_changes');
        Schema::dropIfExists('exam_submissions');
        Schema::dropIfExists('exam_comments');
        Schema::table('exams', function (Blueprint $table) {
            foreach (['out_of', 'marks_deadline', 'next_term_opens', 'hold_for_fees'] as $column) {
                if (Schema::hasColumn('exams', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
