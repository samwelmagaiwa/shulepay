<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationaryRequest extends Model
{
    protected $fillable = [
        'school_id', 'user_id', 'item_id', 'item_name',
        'quantity_requested', 'quantity_provided', 'unit',
        'reason', 'status', 'reviewed_by', 'reviewed_at',
        'provided_at', 'rejection_reason',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'quantity_provided' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'provided_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProvided(): bool
    {
        return $this->status === 'provided';
    }
}
