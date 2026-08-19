<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model {
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'school_id', 'user_id', 'staff_number', 'full_name', 'role',
        'department', 'basic_salary_cents', 'hire_date', 'status',
    ];

    protected $casts = [
        'basic_salary_cents' => MoneyCast::class,
        'hire_date'          => 'date',
    ];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function payrollEntries(): HasMany {
        return $this->hasMany(Payroll::class, 'employee_id');
    }
}
