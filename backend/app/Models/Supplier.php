<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business that sells materials or services to ONE school, recorded by that school.
 *
 * `balance_cents` is a cache of what the school owes (negative = paid in advance); the
 * source of truth is opening balance + approved bills - approved payments, kept by
 * App\Services\Suppliers\SupplierLedger.
 */
class Supplier extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_ON_HOLD, self::STATUS_INACTIVE];

    public const METHODS = ['cash', 'bank', 'mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'cheque', 'other'];

    public const MOBILE_PROVIDERS = ['mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'other'];

    protected $fillable = [
        'school_id', 'code', 'supplier_category_id', 'status', 'name', 'contact_name', 'contact_title',
        'phone', 'alt_phone', 'email', 'address', 'region', 'district',
        'tin', 'vrn', 'business_reg_no', 'vat_registered', 'wht_applicable', 'wht_type',
        'bank_name', 'bank_account_name', 'bank_account_number',
        'mobile_money_provider', 'mobile_money_number', 'mobile_money_name', 'preferred_method',
        'payment_terms_days', 'credit_limit_cents', 'opening_balance_cents', 'balance_cents',
        'rating', 'notes', 'deactivated_at',
    ];

    protected $casts = [
        'balance_cents' => 'integer',
        'opening_balance_cents' => 'integer',
        'credit_limit_cents' => 'integer',
        'payment_terms_days' => 'integer',
        'vat_registered' => 'boolean',
        'wht_applicable' => 'boolean',
        'rating' => 'integer',
        'deactivated_at' => 'datetime',
    ];

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
