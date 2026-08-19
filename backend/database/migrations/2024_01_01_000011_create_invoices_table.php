<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete(); // scoping dimension
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('total_amount_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('arrears_cents')->default(0);
            $table->enum('status', ['paid', 'partial', 'unpaid'])->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'term_id']);
            $table->index(['school_id', 'status']);
            // A student may have invoices from both schools in the same term (cross-school)
            $table->unique(['student_id', 'school_id', 'term_id'], 'invoice_student_school_term_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoices');
    }
};
