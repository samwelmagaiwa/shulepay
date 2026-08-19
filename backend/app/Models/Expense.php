<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model {
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'category_id', 'amount_cents', 'description', 'vendor',
        'receipt_reference', 'expense_date', 'recorded_by', 'approved_by',
        'approved_at', 'status', 'notes',
    ];

    protected $casts = [
        'amount_cents' => MoneyCast::class,
        'expense_date' => 'date',
        'approved_at'  => 'datetime',
    ];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function category(): BelongsTo {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
