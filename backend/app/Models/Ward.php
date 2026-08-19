<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = ['lga_id', 'name'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class, 'ward_id');
    }
}
