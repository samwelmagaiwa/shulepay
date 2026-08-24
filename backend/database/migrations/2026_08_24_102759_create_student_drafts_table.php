<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('school_id')->index();

            // Step 1: Identity
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('birth_certificate_no')->nullable();
            $table->string('nationality')->nullable();
            $table->string('photo')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('address')->nullable();
            $table->string('religion')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('street')->nullable();

            // Step 2: Enrollment
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->string('previous_school')->nullable();
            $table->enum('status', ['active', 'transferred', 'graduated', 'dropped', 'sponsored', 'orphaned'])->default('active');
            $table->text('notes')->nullable();

            // Step 3: Identifications (JSON)
            $table->json('identifications')->nullable();

            // Step 4: Guardians (JSON)
            $table->json('guardians')->nullable();

            // Step 5: Financial
            $table->bigInteger('total_tuition_fee_cents')->nullable();
            $table->string('discount_type')->nullable();
            $table->bigInteger('discount_amount_cents')->nullable();
            $table->bigInteger('opening_balance_cents')->nullable();
            $table->boolean('generate_first_invoice')->default(false);

            // Step 6: Payment History / Migration
            $table->boolean('is_existing_student')->default(false);
            $table->enum('migration_mode', ['detailed', 'lumpsum'])->nullable();
            $table->json('payment_history')->nullable();
            $table->bigInteger('lumpsum_total_charged_cents')->nullable();
            $table->bigInteger('lumpsum_total_paid_cents')->nullable();

            // Tracking
            $table->tinyInteger('current_step')->default(1);
            $table->timestamp('last_accessed_at')->useCurrent();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('school_class_id')->references('id')->on('school_classes')->onDelete('set null');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->onDelete('set null');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_drafts');
    }
};
