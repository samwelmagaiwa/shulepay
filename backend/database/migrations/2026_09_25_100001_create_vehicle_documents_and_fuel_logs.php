<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport: compliance documents (insurance, road licence, inspection...) with
 * expiry dates, and fuel logs.
 *
 * Both tables hang off a vehicle, which already belongs to a school, so they carry
 * no school_id of their own — the same table works in a per-school tenant database
 * and in the shared central one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_documents')) {
            Schema::create('vehicle_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->string('type', 30);
                $table->string('reference_number')->nullable();
                $table->string('provider')->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date');
                $table->unsignedBigInteger('cost_cents')->default(0);
                $table->text('notes')->nullable();
                // The furthest-along expiry alert already sent (30, 14, 7, 0 = expired), so
                // the daily job never repeats itself. Reset whenever the expiry date changes.
                $table->unsignedSmallInteger('last_alert_stage')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->index(['vehicle_id', 'type']);
                $table->index('expiry_date');
            });
        }

        if (! Schema::hasTable('fuel_logs')) {
            Schema::create('fuel_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->date('fuelled_on');
                $table->decimal('litres', 8, 2);
                $table->unsignedBigInteger('cost_cents');
                $table->unsignedInteger('odometer_km');
                // A partial fill cannot be used to work out consumption.
                $table->boolean('full_tank')->default(true);
                $table->string('station')->nullable();
                $table->string('driver_name')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->index(['vehicle_id', 'fuelled_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('vehicle_documents');
    }
};
