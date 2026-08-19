<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model {
    use BelongsToSchool;

    protected $table = 'supplier_payments';

    protected $fillable = [
        'supplier_id', 'school_id', 'amount_cents', 'method',
        'reference', 'payment_date', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'payment_date' => 'date',
    ];

    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
