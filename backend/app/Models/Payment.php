<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'invoice_id', 'student_id', 'school_id', 'receipt_id',
        'amount_cents', 'method', 'reference_number',
        'paid_at', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'paid_at' => 'datetime',
        'method' => PaymentMethod::class,
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
