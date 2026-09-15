<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Revenue against expenses for one school over one period.
 *
 * Both sides are measured over the SAME window. The dashboard's existing
 * total_collected_cents is scoped to the academic year while
 * total_expenses_cents is scoped to the current month, so setting those two
 * side by side would compare a year of income with a month of spending and
 * make the school look far more profitable than it is.
 *
 * Revenue is money actually received (payments by paid_at), not money billed —
 * an unpaid invoice is not revenue. Expenses count only once approved, since a
 * pending expense may still be rejected.
 */
class RevenueExpenseSummary
{
    /**
     * @return array{
     *     period: array{label: string, from: string, to: string},
     *     revenue_cents: int,
     *     expenses_cents: int,
     *     net_cents: int,
     *     expense_ratio: float
     * }
     */
    public function for(?int $schoolId, ?Carbon $today = null): array
    {
        [$label, $from, $to] = $this->period($schoolId, $today ?? Carbon::today());

        $revenue = (int) Payment::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->sum('amount_cents');

        $expenses = (int) Expense::withoutGlobalScopes()
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');

        return [
            'period' => [
                'label' => $label,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'revenue_cents' => $revenue,
            'expenses_cents' => $expenses,
            'net_cents' => $revenue - $expenses,
            // Share of revenue consumed by spending. 0 when nothing has come in,
            // rather than a division by zero or a misleading infinity.
            'expense_ratio' => $revenue > 0 ? round($expenses / $revenue * 100, 2) : 0.0,
        ];
    }

    /**
     * The current academic year's own dates when one is flagged for the school,
     * otherwise the calendar year, so the chart always has a defined window.
     *
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function period(?int $schoolId, Carbon $today): array
    {
        $year = AcademicYear::query()
            ->where('is_current', true)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderByDesc('start_date')
            ->first();

        if ($year && $year->start_date && $year->end_date) {
            return [(string) $year->name, Carbon::parse($year->start_date), Carbon::parse($year->end_date)];
        }

        return [(string) $today->year, $today->copy()->startOfYear(), $today->copy()->endOfYear()];
    }
}
