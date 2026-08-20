<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'school_id', 'name', 'contact_name', 'phone', 'email', 'address', 'balance_cents',
    ];

    public function addDebt(int $cents): void
    {
        $this->update(['balance_cents' => (int) $this->getRawOriginal('balance_cents') + $cents]);
    }

    protected $casts = [
        'balance_cents' => MoneyCast::class,
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
