<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery of materials from a supplier. Receiving it adds the quantities to stock.
 */
class GoodsReceipt extends Model
{
    use BelongsToSchool;

    public const RECEIVED = 'received';

    public const VOID = 'void';

    protected $fillable = [
        'school_id', 'supplier_id', 'grn_number', 'receipt_date', 'delivery_note_no', 'supplier_bill_id',
        'status', 'notes', 'recorded_by', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
