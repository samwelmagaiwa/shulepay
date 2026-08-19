<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transport_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('vehicle_routes')->nullOnDelete();
            $table->enum('direction', ['both', 'morning', 'afternoon'])->default('both');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transport_subscriptions');
    }
};
