<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleRoute extends Model
{
    use BelongsToSchool;

    protected $table = 'vehicle_routes';

    protected $fillable = [
        'school_id', 'name', 'description', 'start_point', 'end_point',
        'distance_km', 'estimated_minutes', 'monthly_fare_cents', 'is_active',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'estimated_minutes' => 'integer',
        'monthly_fare_cents' => MoneyCast::class,
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class, 'route_id');
    }
}
