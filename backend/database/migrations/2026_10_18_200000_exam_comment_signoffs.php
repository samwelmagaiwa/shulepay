<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report-card comments go through two sign-offs per class before an exam can be published: the class
 * teacher submits theirs, then the head teacher (or an academic coordinator) approves. An exam can ask
 * for this (require_comments); older exams keep publishing as before. Same file for central and tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'require_comments')) {
                $table->boolean('require_comments')->default(false);
            }
        });

        if (! Schema::hasTable('exam_signoffs')) {
            Schema::create('exam_signoffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('school_class_id');
                $table->string('stage', 20); // teacher | head
                $table->unsignedBigInteger('signed_by')->nullable();
                $table->string('signed_by_name', 120)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();
                $table->unique(['exam_id', 'school_class_id', 'stage'], 'exam_signoff_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_signoffs');
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'require_comments')) {
                $table->dropColumn('require_comments');
            }
        });
    }
};
