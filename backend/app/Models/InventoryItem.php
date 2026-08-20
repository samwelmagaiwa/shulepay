<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class InventoryItem extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        // Core
        'type', 'school_id', 'category_id', 'name', 'description', 'location', 'is_active', 'notes',

        // Consumable fields
        'unit', 'quantity', 'reorder_level', 'unit_cost_cents',

        // Identity (fixed asset)
        'asset_tag', 'serial_no', 'photo', 'category', 'condition', 'status',

        // Purchase
        'purchase_cost_cents', 'purchase_date', 'supplier_name', 'invoice_no', 'funding_source',

        // Depreciation
        'depreciation_method', 'useful_life_years', 'depreciation_rate',
        'salvage_value_cents', 'accumulated_depreciation_cents', 'current_value_cents',

        // Custody
        'custodian', 'warranty_expiry',

        // Disposal
        'disposal_date', 'disposal_value_cents', 'disposal_reason',

        // Audit
        'registered_by', 'registered_at',
    ];

    protected $casts = [
        // Consumable
        'quantity' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'unit_cost_cents' => MoneyCast::class,
        'is_active' => 'boolean',

        // Fixed asset — plain integers (no MoneyCast, avoids getRawOriginal())
        'purchase_cost_cents' => 'integer',
        'salvage_value_cents' => 'integer',
        'accumulated_depreciation_cents' => 'integer',
        'current_value_cents' => 'integer',
        'disposal_value_cents' => 'integer',

        // Dates
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'disposal_date' => 'date',
        'registered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Auto-compute depreciation for fixed assets on every save
        static::saving(function (InventoryItem $item) {
            if ($item->type === 'fixed_asset') {
                $newValue = $item->computeCurrentValueCents();
                $item->attributes['current_value_cents'] = $newValue;
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isLowStock(): bool
    {
        return $this->type === 'consumable' &&
            (float) $this->quantity <= (float) $this->reorder_level;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? Storage::url($this->photo) : null;
    }

    /**
     * Compute current book value in cents using depreciation formula.
     * Called on every save for fixed_asset type items.
     */
    public function computeCurrentValueCents(): int
    {
        $cost = (int) ($this->attributes['purchase_cost_cents'] ?? 0);

        $purchaseDate = $this->purchase_date instanceof Carbon
            ? $this->purchase_date
            : Carbon::parse($this->attributes['purchase_date'] ?? null);

        if (! $purchaseDate || $cost === 0) {
            return $cost;
        }

        $years = abs($purchaseDate->diffInDays(now())) / 365.25;
        $salvage = (int) ($this->attributes['salvage_value_cents'] ?? 0);

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
     * Generate the next asset tag for a school (queries inventory_items, not assets table).
     */
    public static function nextAssetTag(int $schoolId): string
    {
        $school = School::find($schoolId);
        $prefix = strtoupper($school?->code ?? 'AST').'-AST-';

        $last = self::where('type', 'fixed_asset')
            ->where('asset_tag', 'like', $prefix.'%')
            ->orderByDesc('asset_tag')
            ->value('asset_tag');

        $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
