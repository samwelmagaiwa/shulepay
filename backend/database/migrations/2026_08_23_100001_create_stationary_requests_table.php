<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stationary_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();           // teacher
            $table->foreignId('item_id')->nullable()->constrained('inventory_items'); // pick from stock
            $table->string('item_name');                                              // snapshot of item name
            $table->decimal('quantity_requested', 8, 2);
            $table->decimal('quantity_provided', 8, 2)->nullable();
            $table->string('unit')->default('pcs');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'provided', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('provided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stationary_requests');
    }
};
