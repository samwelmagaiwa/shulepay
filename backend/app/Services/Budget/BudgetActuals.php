<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\Refund;
use Carbon\Carbon;

/**
 * What was really spent against a budget. A line linked to an expense category counts the approved expenses of
 * that category inside the budget's academic year; the refunds line counts refunds paid out in that year; any
 * other line keeps the figure typed by hand. Pending and rejected expenses never count: only signed-off spending is spent.
 */
class BudgetActuals
{
    /** @return array{0: ?Carbon, 1: ?Carbon} the first and last day of the budget's year (null = open ended) */
    public function period(Budget $budget): array
    {
        $year = $budget->academicYear;

        return [$year?->start_date?->copy()->startOfDay(), $year?->end_date?->copy()->endOfDay()];
    }

    public function actualFor(BudgetItem $item, ?Carbon $from, ?Carbon $to): int
    {
        if ($item->kind === 'refunds') {
            return (int) Refund::query()
                ->when($from, fn ($q) => $q->where('refunded_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('refunded_at', '<=', $to))
                ->sum('amount_cents');
        }

        if ($item->expense_category_id) {
            return (int) Expense::query()->where('status', 'approved')->where('category_id', $item->expense_category_id)
                ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
                ->sum('amount_cents');
        }

        return (int) $item->getRawOriginal('actual_cents');
    }

    /**
     * Every line of a budget with its planned and actual amounts and where the actual comes from.
     *
     * @return array{lines: array<int, array<string, mixed>>, planned_cents: int, actual_cents: int, over_count: int}
     */
    public function summary(Budget $budget): array
    {
        [$from, $to] = $this->period($budget);
        $budget->loadMissing('items');

        $lines = $budget->items->map(function (BudgetItem $i) use ($from, $to) {
            $planned = (int) $i->getRawOriginal('planned_cents');
            $actual = $this->actualFor($i, $from, $to);

            return [
                'id' => $i->id,
                'kind' => $i->kind ?: 'expense',
                'expense_category_id' => $i->expense_category_id,
                'category' => $i->category,
                'description' => $i->description,
                'planned_cents' => $planned,
                'actual_cents' => $actual,
                'remaining_cents' => $planned - $actual,
                'percent' => $planned > 0 ? (int) round($actual / $planned * 100) : ($actual > 0 ? 100 : 0),
                'over' => $actual > $planned,
                'source' => $i->kind === 'refunds' ? 'refunds' : ($i->expense_category_id ? 'expenses' : 'manual'),
            ];
        })->values()->all();

        return [
            'lines' => $lines,
            'planned_cents' => array_sum(array_column($lines, 'planned_cents')),
            'actual_cents' => array_sum(array_column($lines, 'actual_cents')),
            'over_count' => count(array_filter($lines, fn ($l) => $l['over'])),
        ];
    }

    /**
     * The warning to show when this expense is (or would be) beyond the planned amount of its budget line, or null.
     * A pending expense is counted on top of what is already approved, so the warning comes before the money is spent.
     */
    public function warningFor(Expense $expense): ?array
    {
        $date = Carbon::parse($expense->expense_date);
        $budget = Budget::with(['academicYear', 'items'])->where('status', 'active')
            ->whereHas('academicYear', fn ($y) => $y->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date))
            ->whereHas('items', fn ($i) => $i->where('expense_category_id', $expense->category_id))
            ->first();
        $item = $budget?->items->firstWhere('expense_category_id', $expense->category_id);
        if (! $item) {
            return null;
        }

        [$from, $to] = $this->period($budget);
        $planned = (int) $item->getRawOriginal('planned_cents');
        $projected = $this->actualFor($item, $from, $to) + ($expense->status === 'pending' ? (int) $expense->amount_cents->cents() : 0);

        if ($projected <= $planned) {
            return null;
        }

        return [
            'budget' => $budget->name,
            'category' => $item->category,
            'planned_cents' => $planned,
            'projected_cents' => $projected,
            'over_cents' => $projected - $planned,
        ];
    }
}
