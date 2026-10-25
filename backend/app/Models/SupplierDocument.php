<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SupplierDocument extends Model
{
    use BelongsToSchool;

    public const TYPES = ['tin_certificate', 'business_licence', 'tax_clearance', 'contract', 'insurance', 'other'];

    public const STATUS_VALID = 'valid';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_EXPIRED = 'expired';

    /** A document counts as "expiring" this many days before it lapses. */
    public const EXPIRING_DAYS = 30;

    protected $fillable = [
        'school_id', 'supplier_id', 'type', 'title', 'reference_number', 'issue_date',
        'expiry_date', 'file_path', 'notes', 'last_alert_stage', 'recorded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'last_alert_stage' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function daysLeft(?Carbon $today = null): ?int
    {
        return $this->expiry_date ? (int) ($today ?? now())->startOfDay()->diffInDays($this->expiry_date, false) : null;
    }

    public function status(?Carbon $today = null): string
    {
        $days = $this->daysLeft($today);

        return match (true) {
            $days === null => self::STATUS_VALID,
            $days < 0 => self::STATUS_EXPIRED,
            $days <= self::EXPIRING_DAYS => self::STATUS_EXPIRING,
            default => self::STATUS_VALID,
        };
    }
}
