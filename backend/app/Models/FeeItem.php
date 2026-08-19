<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeItem extends Model
{
    protected $fillable = ['fee_structure_id', 'name', 'category', 'amount_cents', 'is_optional', 'sort_order'];

    protected $casts = ['amount_cents' => MoneyCast::class, 'is_optional' => 'boolean'];

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }
}
