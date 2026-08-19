<?php
namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model {
    use BelongsToSchool;

    protected $fillable = [
        'student_id', 'school_id', 'term_id', 'academic_year_id',
        'invoice_number', 'total_amount_cents', 'discount_cents', 'arrears_cents',
        'status', 'due_date', 'generated_at', 'generated_by',
    ];
    protected $casts = [
        'total_amount_cents' => MoneyCast::class,
        'discount_cents'     => MoneyCast::class,
        'arrears_cents'      => MoneyCast::class,
        'due_date'           => 'date',
        'generated_at'       => 'datetime',
        'status'             => InvoiceStatus::class,
    ];

    public function student(): BelongsTo     { return $this->belongsTo(Student::class); }
    public function school(): BelongsTo      { return $this->belongsTo(School::class); }
    public function term(): BelongsTo        { return $this->belongsTo(Term::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function lines(): HasMany          { return $this->hasMany(InvoiceLine::class); }
    public function payments(): HasMany       { return $this->hasMany(Payment::class); }
    public function discounts(): HasMany        { return $this->hasMany(Discount::class)->whereNotNull('invoice_id'); }
    public function installmentPlans(): HasMany { return $this->hasMany(InstallmentPlan::class); }

    public function balanceDueCents(): int {
        $total = $this->total_amount_cents->cents()
               + $this->arrears_cents->cents()
               - $this->discount_cents->cents();
        return max(0, $total - $this->paidCents());
    }

    public function paidCents(): int {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum(fn($p) => $p->amount_cents->cents());
        }
        return (int) $this->payments()->sum('amount_cents');
    }

    public function derivedStatus(): InvoiceStatus {
        $balance = $this->balanceDueCents();
        $total   = $this->total_amount_cents->cents()
                 + $this->arrears_cents->cents()
                 - $this->discount_cents->cents();
        if ($balance <= 0)     return InvoiceStatus::Paid;
        if ($balance < $total) return InvoiceStatus::Partial;
        return InvoiceStatus::Unpaid;
    }

    public function syncStatus(): void {
        $this->update(['status' => $this->derivedStatus()]);
    }
}
