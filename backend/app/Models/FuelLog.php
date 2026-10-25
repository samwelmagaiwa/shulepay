<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One refuel of a vehicle. */
class FuelLog extends Model
{
    protected $fillable = [
        'vehicle_id', 'fuelled_on', 'litres', 'cost_cents', 'odometer_km', 'full_tank',
        'station', 'driver_name', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'fuelled_on' => 'date:Y-m-d',
        'litres' => 'float',
        'cost_cents' => 'integer',
        'odometer_km' => 'integer',
        'full_tank' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
