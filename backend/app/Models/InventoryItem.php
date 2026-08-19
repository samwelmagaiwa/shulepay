<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model {
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'school_id', 'category_id', 'name', 'description', 'unit',
        'quantity', 'reorder_level', 'unit_cost_cents', 'location', 'is_active',
    ];

    protected $casts = [
        'quantity'       => 'decimal:2',
        'reorder_level'  => 'decimal:2',
        'unit_cost_cents' => MoneyCast::class,
        'is_active'      => 'boolean',
    ];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function transactions(): HasMany {
        return $this->hasMany(InventoryTransaction::class, 'item_id');
    }

    public function isLowStock(): bool {
        return (float) $this->quantity <= (float) $this->reorder_level;
    }
}
