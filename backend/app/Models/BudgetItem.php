<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetItem extends Model
{
    protected $fillable = ['budget_id', 'category', 'description', 'planned_cents', 'actual_cents', 'expense_category_id', 'kind'];

    protected $casts = [
        'planned_cents' => MoneyCast::class,
        'actual_cents' => MoneyCast::class,
    ];

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
