<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use BelongsToSchool;

    protected $table = 'payroll_entries';

    protected $fillable = [
        'school_id', 'employee_id', 'month', 'year',
        'basic_salary_cents', 'allowances_cents', 'deductions_cents', 'net_salary_cents',
        'payment_method', 'payment_date', 'status', 'recorded_by',
    ];

    protected $casts = [
        'basic_salary_cents' => MoneyCast::class,
        'allowances_cents' => MoneyCast::class,
        'deductions_cents' => MoneyCast::class,
        'net_salary_cents' => MoneyCast::class,
        'payment_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
