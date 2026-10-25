<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money paid to a supplier. `amount_cents` is the value settled against bills; the school
 * may withhold `wht_cents` of it for TRA, so the supplier is actually handed the difference.
 * An approved payment is never deleted: it is voided, with a reason.
 */
class SupplierPayment extends Model
{
    use BelongsToSchool;

    public const PENDING = 'pending_approval';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const VOID = 'void';

    protected $table = 'supplier_payments';

    protected $fillable = [
        'supplier_id', 'school_id', 'amount_cents', 'wht_cents', 'method', 'reference', 'efd_number',
        'payment_date', 'status', 'recorded_by', 'approved_by', 'approved_at',
        'voided_by', 'voided_at', 'void_reason', 'notes',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'wht_cents' => 'integer',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /** What the supplier is actually handed after any tax withheld. */
    public function netCents(): int
    {
        return max(0, $this->amount_cents - $this->wht_cents);
    }
}
