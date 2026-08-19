<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'plate_number', 'make', 'model', 'year',
        'capacity', 'type', 'color', 'status', 'driver_name',
        'driver_phone', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity' => 'integer',
        'status' => 'string',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
