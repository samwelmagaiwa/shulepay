<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school's own list of supplier types. The defaults are seeded from translation keys
 * (so they read in the language being shown); a school can add, rename or hide any.
 */
class SupplierCategory extends Model
{
    use BelongsToSchool;

    /** Seeded for every school on first use; their labels live in the suppliers language files. */
    public const DEFAULTS = [
        'stationery', 'food', 'construction', 'uniforms', 'books', 'fuel_transport',
        'utilities', 'cleaning_security', 'ict', 'services', 'other',
    ];

    protected $fillable = ['school_id', 'key', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function label(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return $this->key ? __('suppliers.categories.'.$this->key) : '';
    }
}
