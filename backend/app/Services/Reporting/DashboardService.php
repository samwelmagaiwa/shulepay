<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function stats(?int $schoolId): array
    {
        $today = Carbon::today();

        $studentCount = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->distinct('student_id')
            ->count('student_id');

        $invoiceQ = Invoice::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        $paymentQ = Payment::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        $todayCollections = (clone $paymentQ)
            ->whereDate('paid_at', $today)
            ->sum('amount_cents');

        $yesterdayCollections = (clone $paymentQ)
            ->whereDate('paid_at', $today->copy()->subDay())
            ->sum('amount_cents');

        $statusCounts = (clone $invoiceQ)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $unpaidInvoices = (clone $invoiceQ)
            ->whereIn('status', ['unpaid', 'partial'])
            ->with('payments')
            ->get();
        $totalOutstanding = $unpaidInvoices->sum(fn ($inv) => $inv->balanceDueCents());

        // Weekly collections (last 7 days)
        $weeklyTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $cents = (clone $paymentQ)->whereDate('paid_at', $day)->sum('amount_cents');
            $weeklyTrend[] = [
                'date' => $day->format('D'),        // Mon, Tue …
                'full_date' => $day->format('Y-m-d'),
                'amount' => (int) $cents,
            ];
        }

        // Payment method breakdown
        $methodRows = (clone $paymentQ)
            ->selectRaw('method, count(*) as cnt, sum(amount_cents) as total')
            ->groupBy('method')
            ->get();
        $methodBreakdown = $methodRows->map(fn ($r) => [
            'method' => $r->method,
            'count' => (int) $r->cnt,
            'total' => (int) $r->total,
        ])->values();

        // Students by school (cross-school view or current school)
        $schoolBreakdown = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('school_id, count(distinct student_id) as cnt')
            ->groupBy('school_id')
            ->with('school:id,name,code,level')
            ->get()
            ->map(function ($r) use ($invoiceQ) {
                $sid = $r->school_id;
                $paidCount = (clone $invoiceQ)
                    ->where('school_id', $sid)->where('status', 'paid')->count();
                $unpaidCount = (clone $invoiceQ)
                    ->where('school_id', $sid)->whereIn('status', ['unpaid', 'partial'])->count();

                return [
                    'school' => $r->school->name ?? 'Unknown',
                    'code' => $r->school->code ?? '',
                    'count' => (int) $r->cnt,
                    'paid_count' => (int) $paidCount,
                    'unpaid_count' => (int) $unpaidCount,
                    'previous_count' => 0,
                    'prev_paid_count' => 0,
                    'prev_unpaid_count' => 0,
                    'trend' => 0,
                ];
            })->values();

        // Students by class (for pieStats.age_groups)
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

        // Top 5 highest debtors
        $topDebtors = (clone $invoiceQ)
            ->whereIn('status', ['unpaid', 'partial'])
            ->with(['student', 'payments'])
            ->get()
            ->sortByDesc(fn ($inv) => $inv->balanceDueCents())
            ->take(5)
            ->values()
            ->map(fn ($inv) => [
                'student' => $inv->student?->fullName(),
                'invoice' => $inv->invoice_number,
                'balance_cents' => $inv->balanceDueCents(),
                'status' => $inv->status->value,
            ]);

        // Current academic year for total collected calculation
        $currentYear = AcademicYear::withoutGlobalScopes()
            ->where('is_current', true)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->first();

        $totalCollectedCents = (clone $paymentQ)
            ->when($currentYear, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('academic_year_id', $currentYear->id)))
            ->sum('amount_cents');

        // Total expenses approved this month
        $totalExpensesCents = Expense::withoutGlobalScopes()
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereMonth('expense_date', $today->month)
            ->whereYear('expense_date', $today->year)
            ->sum('amount_cents');

        // Recent 5 payments with student name, amount, method, paid_at
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

        // Payment trend: last 6 months [{month, total_cents}]
        $paymentTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $today->copy()->startOfMonth()->subMonths($i);
            $totalCents = (clone $paymentQ)
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('amount_cents');
            $paymentTrend[] = [
                'month' => $month->format('Y-m'),
                'total_cents' => (int) $totalCents,
            ];
        }

        // Collection rate: collected / (collected + outstanding) * 100
        $totalForRate = (int) $totalCollectedCents + (int) $totalOutstanding;
        $collectionRate = $totalForRate > 0
            ? round(((int) $totalCollectedCents / $totalForRate) * 100, 2)
            : 0;

        return [
            // Required fields per spec
            'total_students' => $studentCount,
            'total_collected_cents' => (int) $totalCollectedCents,
            'total_outstanding_cents' => (int) $totalOutstanding,
            'total_expenses_cents' => (int) $totalExpensesCents,
            'recent_payments' => $recentPayments,
            'payment_trend' => $paymentTrend,
            'collection_rate' => $collectionRate,
            // Additional fields retained for backwards compatibility
            'today_collections' => (int) $todayCollections,
            'yesterday_collections' => (int) $yesterdayCollections,
            'paid_invoices' => (int) ($statusCounts['paid'] ?? 0),
            'partial_invoices' => (int) ($statusCounts['partial'] ?? 0),
            'unpaid_invoices' => (int) ($statusCounts['unpaid'] ?? 0),
            'weekly_trend' => $weeklyTrend,
            'method_breakdown' => $methodBreakdown,
            'school_breakdown' => $schoolBreakdown,
            'class_breakdown' => $classBreakdown,
            'top_debtors' => $topDebtors,
        ];
    }
}
