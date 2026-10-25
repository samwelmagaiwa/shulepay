<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBillItem extends Model
{
    protected $fillable = [
        'supplier_bill_id', 'description', 'quantity', 'unit', 'unit_price_cents',
        'line_total_cents', 'inventory_item_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_cents' => 'integer',
        'line_total_cents' => 'integer',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
