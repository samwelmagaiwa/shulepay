<?php
namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPlan extends Model
{
    protected $fillable = [
        'invoice_id',
        'student_id',
        'installment_number',
        'due_date',
        'amount_cents',
        'paid_amount_cents',
        'status',
    ];

    protected $casts = [
        'amount_cents'      => MoneyCast::class,
        'paid_amount_cents' => MoneyCast::class,
        'due_date'          => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Returns true if this specific installment row is fully paid. */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** Count of paid installments for same invoice. */
    public function paidSiblingCount(): int
    {
        return static::where('invoice_id', $this->invoice_id)
            ->where('status', 'paid')
            ->count();
    }

    /** Total installments for same invoice. */
    public function totalSiblingCount(): int
    {
        return static::where('invoice_id', $this->invoice_id)->count();
    }
}
