<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * What a supplier invoiced the school. Nothing is owed until the owner approves it.
 */
class SupplierBill extends Model
{
    use BelongsToSchool, SoftDeletes;

    public const PENDING = 'pending_approval';

    public const APPROVED = 'approved';

    public const PART_PAID = 'part_paid';

    public const PAID = 'paid';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    /** Bills that count as owed (approved, whether or not fully settled). */
    public const OWED = [self::APPROVED, self::PART_PAID, self::PAID];

    /** Bills still waiting to be paid. */
    public const OPEN = [self::APPROVED, self::PART_PAID];

    protected $fillable = [
        'school_id', 'supplier_id', 'bill_number', 'bill_date', 'due_date',
        'subtotal_cents', 'vat_cents', 'total_cents', 'paid_cents', 'status',
        'expense_category_id', 'expense_id', 'academic_year_id', 'term_id',
        'attachment_path', 'notes', 'recorded_by', 'approved_by', 'approved_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal_cents' => 'integer',
        'vat_cents' => 'integer',
        'total_cents' => 'integer',
        'paid_cents' => 'integer',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->isOpen() && $this->due_date !== null && $this->due_date->lt(($today ?? now())->startOfDay());
    }
}
