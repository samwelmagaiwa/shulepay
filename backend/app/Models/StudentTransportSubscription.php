<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransportSubscription extends Model {
    protected $fillable = [
        'student_id', 'vehicle_id', 'route_id', 'direction',
        'start_date', 'end_date', 'is_active', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function student(): BelongsTo {
        return $this->belongsTo(Student::class);
    }

    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo {
        return $this->belongsTo(VehicleRoute::class, 'route_id');
    }
}
