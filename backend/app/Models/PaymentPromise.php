<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPromise extends Model
{
    protected $fillable = [
        'student_id', 'invoice_id', 'guardian_id', 'recorded_by',
        'promised_date', 'amount_cents', 'notes', 'status',
        'reminder_sent_day_before', 'reminder_sent_on_day',
    ];

    protected $casts = [
        'promised_date' => 'date',
        'reminder_sent_day_before' => 'boolean',
        'reminder_sent_on_day' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->promised_date->isPast();
    }
}
