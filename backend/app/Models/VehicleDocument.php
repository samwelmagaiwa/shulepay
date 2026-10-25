<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A compliance document of a vehicle (insurance, road licence...) and when it runs out. */
class VehicleDocument extends Model
{
    public const TYPES = ['insurance', 'road_licence', 'inspection', 'permit', 'driver_licence', 'other'];

    /** Days before expiry at which alerts are raised, furthest first. */
    public const ALERT_DAYS = [30, 14, 7];

    public const STATUS_VALID = 'valid';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'vehicle_id', 'type', 'reference_number', 'provider', 'issue_date', 'expiry_date',
        'cost_cents', 'notes', 'last_alert_stage', 'recorded_by',
    ];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'cost_cents' => 'integer',
        'last_alert_stage' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Whole days until expiry: negative once expired, 0 on the day itself. */
    public function daysLeft(?\DateTimeInterface $today = null): int
    {
        $today = Carbon::parse($today ?? now())->startOfDay();

        return (int) $today->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    public function status(?\DateTimeInterface $today = null): string
    {
        $days = $this->daysLeft($today);

        return match (true) {
            $days < 0 => self::STATUS_EXPIRED,
            $days <= max(self::ALERT_DAYS) => self::STATUS_EXPIRING,
            default => self::STATUS_VALID,
        };
    }
}
