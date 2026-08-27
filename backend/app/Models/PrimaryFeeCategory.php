<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrimaryFeeCategory extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'category', 'amount_cents'];

    protected $casts = ['amount_cents' => 'integer'];

    /**
     * Defaults sourced from the school's stated fee policy for Standard 4-6:
     * transport+food+tuition, hostel/boarding, day-scholar eating at school
     * only, and day-scholar using no services at all. Seeded on first read
     * so the admin page opens pre-filled rather than blank.
     */
    public const DEFAULT_AMOUNTS_CENTS = [
        'day_transport_food' => 99_000_000,
        'hostel' => 110_000_000,
        'day_food_only' => 60_000_000,
        'day_none' => 40_000_000,
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
