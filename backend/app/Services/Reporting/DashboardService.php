<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(?int $schoolId): array
    {
        $today = Carbon::today();

        // ── Student count ──────────────────────────────────────────────────────
        // whereHas('student') excludes enrollments whose student was soft-deleted —
        // deleting a student doesn't touch their enrollments directly (see
        // StudentController::destroy), and older deletions predating that fix left
        // enrollments behind still marked 'active'. This check guards against both.
        $studentCount = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereHas('student')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->distinct('student_id')
            ->count('student_id');

        // ── Base query builders ────────────────────────────────────────────────
        $invoiceTable = (new Invoice)->getTable();
        $paymentTable = (new Payment)->getTable();

        $invoiceQ = Invoice::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        $paymentQ = Payment::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        // ── Today / yesterday collections (2 queries) ─────────────────────────
        $todayCollections = (clone $paymentQ)->whereDate('paid_at', $today)->sum('amount_cents');
        $yesterdayCollections = (clone $paymentQ)->whereDate('paid_at', $today->copy()->subDay())->sum('amount_cents');

        // ── Invoice status counts (1 query) ───────────────────────────────────
        $statusCounts = (clone $invoiceQ)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // ── Outstanding balance — DB-level aggregation (1 query) ──────────────
        $totalOutstanding = (clone $invoiceQ)
            ->whereIn('status', ['unpaid', 'partial'])
            ->leftJoin(
                DB::raw("(SELECT invoice_id, SUM(amount_cents) as paid_sum FROM {$paymentTable} GROUP BY invoice_id) as p"),
                'p.invoice_id', '=', "{$invoiceTable}.id"
            )
            ->selectRaw("SUM({$invoiceTable}.total_amount_cents - COALESCE(p.paid_sum, 0)) as outstanding")
            ->value('outstanding') ?? 0;

        // ── Weekly trend — single GROUP BY query (replaces 7 queries) ─────────
        $weekStart = $today->copy()->subDays(6)->startOfDay();
        $weeklyRaw = (clone $paymentQ)
            ->selectRaw('DATE(paid_at) as day, SUM(amount_cents) as total')
            ->where('paid_at', '>=', $weekStart)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $weeklyTrend[] = [
                'date' => $day->format('D'),
                'full_date' => $day->format('Y-m-d'),
                'amount' => (int) ($weeklyRaw[$day->format('Y-m-d')] ?? 0),
            ];
        }

        // ── Payment method breakdown (1 query) ────────────────────────────────
        $methodBreakdown = (clone $paymentQ)
            ->selectRaw('method, count(*) as cnt, sum(amount_cents) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count' => (int) $r->cnt,
                'total' => (int) $r->total,
            ])->values();

        // ── School breakdown — bulk pre-fetch (replaces N+1) ──────────────────
        $enrollmentRows = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('school_id, count(distinct student_id) as cnt')
            ->groupBy('school_id')
            ->with('school:id,name,code,level')
            ->get();

        $schoolIds = $enrollmentRows->pluck('school_id')->filter()->values();

        // Fetch paid/unpaid counts for all schools in 2 queries
        $paidBySchool = (clone $invoiceQ)
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'paid')
            ->selectRaw('school_id, count(*) as cnt')
            ->groupBy('school_id')
            ->pluck('cnt', 'school_id');

        $unpaidBySchool = (clone $invoiceQ)
            ->whereIn('school_id', $schoolIds)
            ->whereIn('status', ['unpaid', 'partial'])
            ->selectRaw('school_id, count(*) as cnt')
            ->groupBy('school_id')
            ->pluck('cnt', 'school_id');

        $schoolBreakdown = $enrollmentRows->map(fn ($r) => [
            'school' => $r->school->name ?? 'Unknown',
            'code' => $r->school->code ?? '',
            'count' => (int) $r->cnt,
            'paid_count' => (int) ($paidBySchool[$r->school_id] ?? 0),
            'unpaid_count' => (int) ($unpaidBySchool[$r->school_id] ?? 0),
            'previous_count' => 0,
            'prev_paid_count' => 0,
            'prev_unpaid_count' => 0,
            'trend' => 0,
        ])->values();

        // ── Class breakdown (1 query) ─────────────────────────────────────────
        $classBreakdown = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('school_class_id, count(distinct student_id) as cnt')
            ->groupBy('school_class_id')
            ->with('schoolClass:id,name')
            ->get()
            ->mapWithKeys(fn ($r) => [
                strtolower(str_replace(' ', '_', $r->schoolClass->name ?? 'unknown')) => (int) $r->cnt,
            ])->toArray();

        // ── Class fee collection breakdown (1 query) ───────────────────────────
        // Distinct from $classBreakdown above, which counts enrolled students —
        // this sums money actually collected per class, joining through
        // invoices → students → enrollments → school_classes the same way
        // ReportController::collections' by_class does.
        $classFeeBreakdown = Payment::allSchools()
            ->join($invoiceTable, "{$invoiceTable}.id", '=', "{$paymentTable}.invoice_id")
            ->join('enrollments', function ($join) use ($invoiceTable) {
                $join->on('enrollments.student_id', '=', "{$invoiceTable}.student_id")
                    ->where('enrollments.status', 'active');
            })
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.school_class_id')
            ->when($schoolId, fn ($q) => $q->where("{$paymentTable}.school_id", $schoolId))
            ->selectRaw("school_classes.name as class_name, sum({$paymentTable}.amount_cents) as total")
            ->groupBy('school_classes.name')
            ->get()
            ->mapWithKeys(fn ($r) => [
                strtolower(str_replace(' ', '_', $r->class_name ?? 'unknown')) => (int) $r->total,
            ])->toArray();

        // ── Top 5 debtors — DB-level sort (replaces full load + PHP sort) ─────
        $topDebtors = (clone $invoiceQ)
            ->whereIn('status', ['unpaid', 'partial'])
            ->leftJoin(
                DB::raw("(SELECT invoice_id, SUM(amount_cents) as paid_sum FROM {$paymentTable} GROUP BY invoice_id) as pd"),
                'pd.invoice_id', '=', "{$invoiceTable}.id"
            )
            ->selectRaw("{$invoiceTable}.*, ({$invoiceTable}.total_amount_cents - COALESCE(pd.paid_sum, 0)) as balance")
            ->orderByDesc('balance')
            ->limit(5)
            ->with('student')
            ->get()
            ->map(fn ($inv) => [
                'student' => $inv->student?->fullName(),
                'invoice' => $inv->invoice_number,
                'balance_cents' => (int) $inv->balance,
                'status' => $inv->status instanceof \BackedEnum ? $inv->status->value : $inv->status,
            ])->values();

        // ── Academic year + total collected (2 queries) ───────────────────────
        $currentYear = AcademicYear::withoutGlobalScopes()
            ->where('is_current', true)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->first();

        $totalCollectedCents = (clone $paymentQ)
            ->when($currentYear, fn ($q) => $q->whereHas(
                'invoice', fn ($iq) => $iq->where('academic_year_id', $currentYear->id)
            ))
            ->sum('amount_cents');

        // ── Expenses this month (1 query) ─────────────────────────────────────
        $totalExpensesCents = Expense::withoutGlobalScopes()
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereMonth('expense_date', $today->month)
            ->whereYear('expense_date', $today->year)
            ->sum('amount_cents');

        // ── Recent 5 payments (1 query) ───────────────────────────────────────
        $recentPayments = (clone $paymentQ)
            ->with('student')
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'student' => $p->student?->fullName(),
                'amount_cents' => (int) $p->amount_cents->cents(),
                'method' => $p->method instanceof \BackedEnum ? $p->method->value : $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->values();

        // ── Payment trend last 6 months — single GROUP BY (replaces 6 queries) ─
        $trendStart = $today->copy()->startOfMonth()->subMonths(5);
        $trendRaw = (clone $paymentQ)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as ym, SUM(amount_cents) as total")
            ->where('paid_at', '>=', $trendStart)
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym');

        $paymentTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $today->copy()->startOfMonth()->subMonths($i);
            $paymentTrend[] = [
                'month' => $month->format('Y-m'),
                'total_cents' => (int) ($trendRaw[$month->format('Y-m')] ?? 0),
            ];
        }

        // ── Collection rate ────────────────────────────────────────────────────
        $totalForRate = (int) $totalCollectedCents + (int) $totalOutstanding;
        $collectionRate = $totalForRate > 0
            ? round(((int) $totalCollectedCents / $totalForRate) * 100, 2)
            : 0;

        return [
            'total_students' => $studentCount,
            'total_collected_cents' => (int) $totalCollectedCents,
            'total_outstanding_cents' => (int) $totalOutstanding,
            'total_expenses_cents' => (int) $totalExpensesCents,
            'recent_payments' => $recentPayments,
            'payment_trend' => $paymentTrend,
            'collection_rate' => $collectionRate,
            'today_collections' => (int) $todayCollections,
            'yesterday_collections' => (int) $yesterdayCollections,
            'paid_invoices' => (int) ($statusCounts['paid'] ?? 0),
            'partial_invoices' => (int) ($statusCounts['partial'] ?? 0),
            'unpaid_invoices' => (int) ($statusCounts['unpaid'] ?? 0),
            'weekly_trend' => $weeklyTrend,
            'method_breakdown' => $methodBreakdown,
            'school_breakdown' => $schoolBreakdown,
            'class_breakdown' => $classBreakdown,
            'class_fee_breakdown_cents' => $classFeeBreakdown,
            'top_debtors' => $topDebtors,
        ];
    }
}
