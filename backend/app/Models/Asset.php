<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        // Legacy
        'school_id', 'name', 'category', 'cost_cents', 'current_value_cents',
        'purchase_date', 'condition', 'serial_number', 'location', 'notes',

        // Identity
        'asset_tag', 'quantity', 'serial_no', 'photo',

        // Purchase
        'purchase_cost_cents', 'supplier_name', 'invoice_no', 'funding_source',

        // Depreciation
        'depreciation_method', 'useful_life_years', 'depreciation_rate',
        'salvage_value_cents', 'accumulated_depreciation_cents',

        // Custody
        'custodian', 'warranty_expiry',

        // Status
        'status',

        // Disposal
        'disposal_date', 'disposal_value_cents', 'disposal_reason',

        // Audit
        'registered_by', 'registered_at',
    ];

    protected $casts = [
        'cost_cents' => MoneyCast::class,
        'current_value_cents' => MoneyCast::class,
        'purchase_cost_cents' => MoneyCast::class,
        'salvage_value_cents' => MoneyCast::class,
        'accumulated_depreciation_cents' => MoneyCast::class,
        'disposal_value_cents' => MoneyCast::class,
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'disposal_date' => 'date',
        'registered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Asset $asset) {
            $newValue = $asset->computeCurrentValueCents();
            $asset->attributes['current_value_cents'] = $newValue;
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Compute current book value in cents using depreciation formula.
     * Called on every save to update current_value_cents.
     */
    public function computeCurrentValueCents(): int
    {
        $cost = (int) $this->getRawOriginal('purchase_cost_cents');

        if (! $this->purchase_date || $cost === 0) {
            return $cost;
        }

        $years = now()->diffInDays($this->purchase_date) / 365.25;
        $salvage = (int) $this->getRawOriginal('salvage_value_cents');

        $depreciation = 0;

        if ($this->depreciation_method === 'straight_line' && $this->useful_life_years > 0) {
            $annualDep = ($cost - $salvage) / $this->useful_life_years;
            $depreciation = (int) min($annualDep * $years, $cost - $salvage);
        } elseif ($this->depreciation_method === 'reducing_balance' && $this->depreciation_rate > 0) {
            $rate = $this->depreciation_rate / 100;
            $currentVal = $cost;
            $wholeYears = (int) $years;

            for ($i = 0; $i < $wholeYears; $i++) {
                $dep = (int) ($currentVal * $rate);
                $depreciation += $dep;
                $currentVal -= $dep;
            }

            $partial = $years - $wholeYears;
            if ($partial > 0) {
                $dep = (int) ($currentVal * $rate * $partial);
                $depreciation += $dep;
            }
        }

        $this->attributes['accumulated_depreciation_cents'] = $depreciation;

        return max($salvage, $cost - $depreciation);
    }

    /**
     * Auto-generate asset_tag from school code if not set.
     */
    public static function nextAssetTag(int $schoolId): string
    {
        $school = School::find($schoolId);
        $prefix = strtoupper($school?->code ?? 'AST').'-AST-';
        $last = self::where('asset_tag', 'like', $prefix.'%')
            ->orderByDesc('asset_tag')
            ->value('asset_tag');
        $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
