<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model {
    protected $table = 'vehicle_maintenance';

    protected $fillable = [
        'vehicle_id', 'type', 'description', 'cost_cents', 'service_date',
        'next_service_date', 'odometer_km', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'cost_cents'       => MoneyCast::class,
        'service_date'     => 'date',
        'next_service_date' => 'date',
        'odometer_km'      => 'integer',
    ];

    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
