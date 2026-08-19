<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'item_id', 'school_id', 'type', 'quantity', 'notes',
        'reference', 'transaction_date', 'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
