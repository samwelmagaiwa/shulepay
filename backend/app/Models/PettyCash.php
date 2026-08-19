<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCash extends Model {
    use BelongsToSchool;

    protected $table = 'petty_cash_entries';

    protected $fillable = [
        'school_id', 'type', 'amount_cents', 'description',
        'reference', 'balance_after_cents', 'recorded_by', 'entry_date',
    ];

    protected $casts = [
        'amount_cents'       => MoneyCast::class,
        'balance_after_cents' => MoneyCast::class,
        'entry_date'         => 'date',
    ];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
