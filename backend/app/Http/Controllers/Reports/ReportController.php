<?php

namespace App\Http\Controllers\Reports;

use App\Exports\GenericArrayExport;
use App\Exports\OutstandingDebtsExport;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SupplierPayment;
use App\Services\Reporting\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportExportService $exporter) {}

    /**
     * Every report query below used to scope by school ONLY when an explicit
     * `school_id` query param was sent — never sent by the frontend, so every
     * report silently aggregated across every school in the system regardless
     * of whichever school was active in the UI. This falls back to the
     * active-school context (set via the X-School-Id header) the same way
     * resolveSchool() already did for exports, but was never applied to the
     * actual report queries themselves.
     */
    private function activeSchoolId(Request $request): ?int
    {
        if ($request->filled('school_id')) {
            return $request->integer('school_id');
        }

        return app()->bound('active_school') ? app('active_school')?->id : null;
    }

    // ─────────────────────────────────────────────────────────
    //  1. Collections
    // ─────────────────────────────────────────────────────────

    public function collections(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'group_by' => 'nullable|in:day,week,month,class',
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        $groupBy = $request->input('group_by', 'day');
        $schoolId = $this->activeSchoolId($request);

        // Base payment query (bypass school scope if no school bound)
        $base = Payment::allSchools()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($schoolId, fn ($q) => $q->where('payments.school_id', $schoolId));

        // Summary totals
        $totalPayments = (clone $base)->count();
        $totalAmount = (clone $base)->sum('amount_cents');

        // Invoice counts by status for the period
        $invoiceBase = Invoice::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));

        $invoiceCount = (clone $invoiceBase)->count();
        $paidCount = (clone $invoiceBase)->where('status', 'paid')->count();
        $partialCount = (clone $invoiceBase)->where('status', 'partial')->count();
        $unpaidCount = (clone $invoiceBase)->where('status', 'unpaid')->count();

        // Total outstanding — current balance across all unpaid/partial invoices
        // (not period-scoped: it's "what's owed right now", same figure the
        // dashboard's Outstanding Debt card shows).
        $paymentTable = (new Payment)->getTable();
        $invoiceTable = (new Invoice)->getTable();
        $totalOutstanding = (clone $invoiceBase)
            ->whereIn('status', ['unpaid', 'partial'])
            ->leftJoin(
                DB::raw("(SELECT invoice_id, SUM(amount_cents) as paid_sum FROM {$paymentTable} GROUP BY invoice_id) as p"),
                'p.invoice_id', '=', "{$invoiceTable}.id"
            )
            ->selectRaw("SUM({$invoiceTable}.total_amount_cents - COALESCE(p.paid_sum, 0)) as outstanding")
            ->value('outstanding') ?? 0;
        // An overpaid invoice can push the sum negative — "debt" is never negative,
        // that would represent a credit balance, a different concept this figure
        // doesn't track.
        $totalOutstanding = max(0, (int) $totalOutstanding);

        // Amount behind each summary card's count, so every card can show
        // "count | amount" instead of just one or the other.
        $paidAmountCents = (int) (clone $invoiceBase)->where('status', 'paid')->sum('total_amount_cents');
        $partialInvoiceIds = (clone $invoiceBase)->where('status', 'partial')->pluck('id');
        $partialPaidAmountCents = (int) Payment::allSchools()
            ->whereIn('invoice_id', $partialInvoiceIds)
            ->sum('amount_cents');

        // $groupBy is validated above as in:day,week,month,class — safe to use in DB::raw
        $periodExpr = match ($groupBy) {
            'week' => DB::raw("DATE_FORMAT(paid_at, '%x-W%v') as period"),
            'month' => DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as period"),
            default => DB::raw('DATE(paid_at) as period'),  // day & class both row by day
        };

        $rows = (clone $base)
            ->select(
                $periodExpr,
                DB::raw('SUM(amount_cents) as amount_cents'),
                DB::raw('COUNT(*) as payment_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($r) => [
                'period' => $r->period,
                'amount_cents' => (int) $r->getRawOriginal('amount_cents'),
                'payment_count' => (int) $r->getRawOriginal('payment_count'),
            ])
            ->toArray();

        // Per-period "Total Debts" (remaining balance, as of now, on invoices
        // touched by a payment that period) and "Total Partial Paid" (payment
        // amounts that period against invoices still in 'partial' status).
        // Built in PHP rather than a single grouped SQL query: joining
        // payments -> invoices and summing balance per period would double-count
        // an invoice's balance for every payment it received that period, since
        // balance is a property of the invoice, not of any one payment.
        $touchedRows = (clone $base)
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->select(
                $periodExpr,
                'invoices.id as touched_invoice_id',
                'invoices.status as touched_invoice_status',
                'invoices.total_amount_cents as touched_invoice_total_cents',
                'payments.amount_cents as touched_payment_amount_cents'
            )
            ->get();

        $touchedInvoiceIds = $touchedRows->pluck('touched_invoice_id')->unique();
        $paidSumsByInvoice = Payment::allSchools()
            ->whereIn('invoice_id', $touchedInvoiceIds)
            ->selectRaw('invoice_id, SUM(amount_cents) as paid_sum')
            ->groupBy('invoice_id')
            ->pluck('paid_sum', 'invoice_id');

        $debtByPeriod = [];
        $partialPaidByPeriod = [];
        $seenInvoicePeriod = [];

        foreach ($touchedRows as $r) {
            $period = $r->period;
            $invId = $r->touched_invoice_id;
            $status = $r->touched_invoice_status;

            $debtByPeriod[$period] ??= 0;
            $partialPaidByPeriod[$period] ??= 0;

            $dedupeKey = $period.':'.$invId;
            if (in_array($status, ['unpaid', 'partial'], true) && ! isset($seenInvoicePeriod[$dedupeKey])) {
                $balance = max(0, (int) $r->touched_invoice_total_cents - (int) ($paidSumsByInvoice[$invId] ?? 0));
                $debtByPeriod[$period] += $balance;
            }
            $seenInvoicePeriod[$dedupeKey] = true;

            if ($status === 'partial') {
                $partialPaidByPeriod[$period] += (int) $r->touched_payment_amount_cents;
            }
        }

        $rows = array_map(function ($row) use ($debtByPeriod, $partialPaidByPeriod) {
            $row['total_debt_cents'] = (int) ($debtByPeriod[$row['period']] ?? 0);
            $row['total_partial_paid_cents'] = (int) ($partialPaidByPeriod[$row['period']] ?? 0);

            return $row;
        }, $rows);

        // By payment method
        $byMethod = (clone $base)
            ->select('method', DB::raw('SUM(amount_cents) as amount_cents'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'amount_cents' => (int) $r->getRawOriginal('amount_cents'),
                'count' => (int) $r->count,
            ])
            ->toArray();

        // By class — join through invoices → students → enrollments → school_classes
        $byClass = Payment::allSchools()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'invoices.student_id')
                    ->where('enrollments.status', 'active');
            })
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.school_class_id')
            ->whereBetween('payments.paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($schoolId, fn ($q) => $q->where('payments.school_id', $schoolId))
            ->select('school_classes.name as class', DB::raw('SUM(payments.amount_cents) as amount_cents'))
            ->groupBy('school_classes.name')
            ->orderBy('school_classes.name')
            ->get()
            ->map(fn ($r) => [
                'class' => $r->class,
                'amount_cents' => (int) $r->getRawOriginal('amount_cents'),
            ])
            ->toArray();

        // ── Discounts by type (sibling / staff / sponsor / other) ─────────────
        // Scoped via invoice->school_id (Discount has no school_id of its own),
        // matching how every other figure in this report is school-scoped.
        $discountTypes = ['sibling', 'staff', 'sponsor', 'other'];
        $discountRows = Discount::query()
            ->join('invoices', 'invoices.id', '=', 'discounts.invoice_id')
            ->when($schoolId, fn ($q) => $q->where('invoices.school_id', $schoolId))
            ->select('discounts.type', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(discounts.amount_cents) as amount_cents'))
            ->groupBy('discounts.type')
            ->get()
            ->keyBy('type');

        $byDiscountType = array_map(function ($type) use ($discountRows) {
            $row = $discountRows[$type] ?? null;

            return [
                'type' => $type,
                'count' => $row ? (int) $row->cnt : 0,
                'amount_cents' => $row ? (int) $row->getRawOriginal('amount_cents') : 0,
            ];
        }, $discountTypes);

        // ── Sponsorships by type (half / full / full_paid) ─────────────────────
        // Counted off active enrollments (a student can only be actively
        // enrolled once), same student-count basis the dashboard uses.
        $sponsorshipTypes = ['half', 'full', 'full_paid'];
        $sponsorshipRows = Enrollment::withoutGlobalScope('school')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.status', 'active')
            ->when($schoolId, fn ($q) => $q->where('enrollments.school_id', $schoolId))
            ->whereIn('students.sponsorship_type', $sponsorshipTypes)
            ->select('students.sponsorship_type', DB::raw('COUNT(DISTINCT students.id) as cnt'))
            ->groupBy('students.sponsorship_type')
            ->get()
            ->keyBy('sponsorship_type');

        // Real money collected (this period) from each sponsorship group's
        // invoices — 'half' students are billed at a reduced rate and pay
        // through the normal invoice flow (no separate "sponsor payment"
        // record exists for them), so a bare student count alone showed "0"
        // for the money column even though real payments were being made.
        // 'full' has no amount at all (fully free, never billed).
        $collectedBySponsorship = (clone $base)
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('students', 'students.id', '=', 'invoices.student_id')
            ->whereIn('students.sponsorship_type', $sponsorshipTypes)
            ->select('students.sponsorship_type', DB::raw('SUM(payments.amount_cents) as amount_cents'))
            ->groupBy('students.sponsorship_type')
            ->get()
            ->keyBy('sponsorship_type');

        $bySponsorshipType = array_map(function ($type) use ($sponsorshipRows, $collectedBySponsorship) {
            $countRow = $sponsorshipRows[$type] ?? null;
            $amountRow = $collectedBySponsorship[$type] ?? null;

            return [
                'type' => $type,
                'count' => $countRow ? (int) $countRow->cnt : 0,
                'amount_cents' => $amountRow ? (int) $amountRow->getRawOriginal('amount_cents') : 0,
            ];
        }, $sponsorshipTypes);

        $data = [
            'summary' => [
                'total_payments' => $totalPayments,
                'total_amount_cents' => (int) $totalAmount,
                'invoice_count' => $invoiceCount,
                'paid_count' => $paidCount,
                'partial_count' => $partialCount,
                'unpaid_count' => $unpaidCount,
                'total_outstanding_cents' => (int) $totalOutstanding,
                'debt_invoice_count' => $unpaidCount + $partialCount,
                'paid_amount_cents' => $paidAmountCents,
                'partial_paid_amount_cents' => $partialPaidAmountCents,
            ],
            'rows' => $rows,
            'by_method' => $byMethod,
            'by_class' => $byClass,
            'by_discount_type' => $byDiscountType,
            'by_sponsorship_type' => $bySponsorshipType,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ];

        return response()->json($data);
    }

    // ─────────────────────────────────────────────────────────
    //  2. Debtor Aging
    // ─────────────────────────────────────────────────────────

    public function debtorAging(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'as_of' => 'nullable|date',
        ]);

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : today();
        $schoolId = $this->activeSchoolId($request);

        // Fetch unpaid / partial invoices with student info
        $invoices = Invoice::allSchools()
            ->with(['student.currentEnrollment.schoolClass', 'student.guardians', 'term'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get();

        // Group by student, track oldest invoice due_date per student
        $studentMap = [];

        foreach ($invoices as $inv) {
            $sid = $inv->student_id;
            $balance = $inv->balanceDueCents();

            if ($balance <= 0) {
                continue;
            }

            // Migrated/historical invoices (StudentRegistrationService::
            // importPaymentHistory) are created with due_date=null — there was
            // no agreed due date to import. Fall back to when the invoice was
            // generated so it still ages sensibly instead of crashing.
            $dueDate = $inv->due_date ?? $inv->generated_at ?? $inv->created_at;
            $age = (int) $dueDate->diffInDays($asOf, false);
            // Negative age means not yet due — treat as current (0)
            $age = max(0, $age);

            if (! isset($studentMap[$sid])) {
                $enrollment = $inv->student?->currentEnrollment;
                $guardian = $inv->student?->guardians->firstWhere('pivot.is_primary', true) ?? $inv->student?->guardians->first();
                $studentMap[$sid] = [
                    'id' => $inv->student_id,
                    'full_name' => $inv->student?->fullName() ?? '—',
                    'admission_number' => $enrollment?->admission_number ?? '—',
                    'school_class' => $enrollment?->schoolClass?->name ?? '—',
                    'guardian_name' => $guardian ? trim($guardian->first_name.' '.$guardian->last_name) : '—',
                    'guardian_phone' => $guardian->phone ?? '—',
                    'village_street' => $inv->student?->street ?: $inv->student?->address ?: '—',
                    'oldest_invoice_date' => $dueDate->toDateString(),
                    'oldest_age' => $age,
                    'outstanding_cents' => $balance,
                    'terms_not_paid' => [],
                ];
            } else {
                $studentMap[$sid]['outstanding_cents'] += $balance;
                if ($age > $studentMap[$sid]['oldest_age']) {
                    $studentMap[$sid]['oldest_age'] = $age;
                    $studentMap[$sid]['oldest_invoice_date'] = $dueDate->toDateString();
                }
            }
            if ($inv->term && ! in_array($inv->term->name, $studentMap[$sid]['terms_not_paid'], true)) {
                $studentMap[$sid]['terms_not_paid'][] = $inv->term->name;
            }
        }

        $buckets = [
            'current' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_1_30' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_31_60' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_61_90' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'over_90' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
        ];

        foreach ($studentMap as $s) {
            $age = $s['oldest_age'];
            $bucket = match (true) {
                $age <= 0 => 'current',
                $age <= 30 => 'days_1_30',
                $age <= 60 => 'days_31_60',
                $age <= 90 => 'days_61_90',
                default => 'over_90',
            };

            $record = [
                'id' => $s['id'],
                'full_name' => $s['full_name'],
                'admission_number' => $s['admission_number'],
                'school_class' => $s['school_class'],
                'guardian_name' => $s['guardian_name'],
                'guardian_phone' => $s['guardian_phone'],
                'village_street' => $s['village_street'],
                'oldest_invoice_date' => $s['oldest_invoice_date'],
                'oldest_age' => $age,
                'outstanding_cents' => $s['outstanding_cents'],
                'terms_not_paid' => implode(', ', $s['terms_not_paid']),
            ];

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount_cents'] += $s['outstanding_cents'];
            $buckets[$bucket]['students'][] = $record;
        }

        $totalDebtors = count($studentMap);
        $totalOutstanding = array_sum(array_column($studentMap, 'outstanding_cents'));

        return response()->json([
            'summary' => [
                'total_debtors' => $totalDebtors,
                'total_outstanding_cents' => $totalOutstanding,
                'as_of' => $asOf->toDateString(),
            ],
            'buckets' => $buckets,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  3. Income Statement
    // ─────────────────────────────────────────────────────────

    public function incomeStatement(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfYear();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        $schoolId = $this->activeSchoolId($request);

        // Revenue: fee collections (payments) in period
        $feeCollections = Payment::allSchools()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $totalRevenue = (int) $feeCollections;

        // Expenses: approved expenses in period
        $expensesByCategory = Expense::allSchools()
            ->with('category')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->select('category_id', DB::raw('SUM(amount_cents) as amount_cents'))
            ->groupBy('category_id')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category?->name ?? 'Uncategorised',
                'amount_cents' => (int) $r->getRawOriginal('amount_cents'),
            ])
            ->toArray();

        $totalExpenses = array_sum(array_column($expensesByCategory, 'amount_cents'));

        // Payroll: paid payroll in period
        $payrollTotal = Payroll::allSchools()
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('net_salary_cents');

        $payrollTotal = (int) $payrollTotal;
        $totalExpenses += $payrollTotal;

        return response()->json([
            'revenue' => [
                'fee_collections' => (int) $feeCollections,
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'by_category' => $expensesByCategory,
                'payroll' => $payrollTotal,
                'total' => $totalExpenses,
            ],
            'net_income_cents' => $totalRevenue - $totalExpenses,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  4. Balance Sheet
    // ─────────────────────────────────────────────────────────

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'as_of' => 'nullable|date',
        ]);

        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : today();

        $schoolId = $this->activeSchoolId($request);

        // Cash & Bank: all payments received up to as_of
        $cashAndBank = Payment::allSchools()
            ->where('paid_at', '<=', $asOf->copy()->endOfDay())
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        // Receivables: outstanding balance on unpaid/partial invoices as of date
        // (Invoices with due_date <= as_of that still have a balance)
        $receivables = Invoice::allSchools()
            ->whereIn('status', ['unpaid', 'partial'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get()
            ->sum(fn (Invoice $inv) => $inv->balanceDueCents());

        // Fixed assets: sum of current_value_cents
        $fixedAssets = Asset::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('current_value_cents');

        $totalAssets = (int) $cashAndBank + (int) $receivables + (int) $fixedAssets;

        // Liabilities: supplier balances owed (total invoiced to suppliers minus payments)
        // We use approved expenses that are unpaid as a proxy for payables
        $approvedExpenses = Expense::allSchools()
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $supplierPaymentsTotal = SupplierPayment::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $payables = max(0, (int) $approvedExpenses - (int) $supplierPaymentsTotal);
        $totalLiab = $payables;

        // Equity = Assets - Liabilities
        $retained = $totalAssets - $totalLiab;
        $totalEquity = $retained;

        return response()->json([
            'assets' => [
                'cash_and_bank' => [
                    'description' => 'Received fees (payments total)',
                    'amount_cents' => (int) $cashAndBank,
                ],
                'receivables' => [
                    'description' => 'Outstanding invoices',
                    'amount_cents' => (int) $receivables,
                ],
                'fixed_assets' => [
                    'description' => 'Asset register book value',
                    'amount_cents' => (int) $fixedAssets,
                ],
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'payables' => [
                    'description' => 'Supplier balances owed',
                    'amount_cents' => $payables,
                ],
                'total' => $totalLiab,
            ],
            'equity' => [
                'retained' => $retained,
                'total' => $totalEquity,
            ],
            'as_of' => $asOf->toDateString(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  5. Cash Flow
    // ─────────────────────────────────────────────────────────

    public function cashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        $schoolId = $this->activeSchoolId($request);

        // Operating inflows: fee collections
        $feeCollections = Payment::allSchools()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        // Operating outflows: approved expenses
        $expensePayments = Expense::allSchools()
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        // Payroll outflows: paid payroll
        $payrollPayments = Payroll::allSchools()
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('net_salary_cents');

        // Supplier payments
        $supplierPayments = SupplierPayment::allSchools()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $feeCollections = (int) $feeCollections;
        $expensePayments = (int) $expensePayments;
        $payrollPayments = (int) $payrollPayments;
        $supplierPayments = (int) $supplierPayments;

        $operatingNet = $feeCollections - $expensePayments - $payrollPayments - $supplierPayments;

        // Investing: asset purchases in period
        $assetPurchases = Asset::allSchools()
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('cost_cents');

        $assetPurchases = (int) $assetPurchases;
        $investingNet = -$assetPurchases;

        return response()->json([
            'operating' => [
                'fee_collections' => $feeCollections,
                'expense_payments' => $expensePayments,
                'payroll_payments' => $payrollPayments,
                'supplier_payments' => $supplierPayments,
                'net' => $operatingNet,
            ],
            'investing' => [
                'asset_purchases' => $assetPurchases,
                'net' => $investingNet,
            ],
            'net_change_cents' => $operatingNet + $investingNet,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  6b. Collections By Class
    // ─────────────────────────────────────────────────────────

    public function byClass(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
        ]);

        $schoolId = $this->activeSchoolId($request);

        $classes = SchoolClass::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('sort_order')
            ->get();

        $rows = $classes->map(function (SchoolClass $class) use ($request) {
            $studentIds = Enrollment::where('school_class_id', $class->id)
                ->where('status', 'active')
                ->when(
                    $request->filled('academic_year_id'),
                    fn ($q) => $q->where('academic_year_id', $request->integer('academic_year_id'))
                )
                ->pluck('student_id');

            $invoices = Invoice::allSchools()->whereIn('student_id', $studentIds)->get();

            $billed = $invoices->sum(
                fn (Invoice $inv) => $inv->total_amount_cents->cents()
                    + $inv->arrears_cents->cents()
                    - $inv->discount_cents->cents()
            );
            $collected = $invoices->sum(fn (Invoice $inv) => $inv->paidCents());

            return [
                'class_name' => $class->name,
                'student_count' => $studentIds->count(),
                'total_billed_cents' => (int) $billed,
                'total_collected_cents' => (int) $collected,
                'total_outstanding_cents' => max(0, (int) $billed - (int) $collected),
                'paid_count' => $invoices->filter(fn (Invoice $inv) => $inv->status->value === 'paid')->count(),
                'partial_count' => $invoices->filter(fn (Invoice $inv) => $inv->status->value === 'partial')->count(),
                'unpaid_count' => $invoices->filter(fn (Invoice $inv) => $inv->status->value === 'unpaid')->count(),
            ];
        })->values();

        return response()->json(['rows' => $rows]);
    }

    // ─────────────────────────────────────────────────────────
    //  6c. Expenses vs Collections
    // ─────────────────────────────────────────────────────────

    public function expensesVsCollections(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $schoolId = $this->activeSchoolId($request);
        $year = $request->integer('year') ?: (int) now()->format('Y');

        // pluck()/attribute access on an aggregated column still runs it through
        // the model's Money cast (casts apply by attribute NAME, not by where the
        // value came from) — get() + getRawOriginal() sidesteps that instead of
        // crashing on "Object of class Money could not be converted to int".
        $collectionsByMonth = Payment::allSchools()
            ->whereYear('paid_at', $year)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('MONTH(paid_at) as month, SUM(amount_cents) as amount_cents')
            ->groupBy('month')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->month => (int) $r->getRawOriginal('amount_cents')]);

        $expensesByMonth = Expense::allSchools()
            ->where('status', 'approved')
            ->whereYear('expense_date', $year)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('MONTH(expense_date) as month, SUM(amount_cents) as amount_cents')
            ->groupBy('month')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->month => (int) $r->getRawOriginal('amount_cents')]);

        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $collections = (int) ($collectionsByMonth[$m] ?? 0);
            $expenses = (int) ($expensesByMonth[$m] ?? 0);
            $rows[] = [
                'month' => $m,
                'collections_cents' => $collections,
                'expenses_cents' => $expenses,
                'net_cents' => $collections - $expenses,
            ];
        }

        return response()->json(['rows' => $rows, 'year' => $year]);
    }

    // ─────────────────────────────────────────────────────────
    //  6. Student Statement
    // ─────────────────────────────────────────────────────────

    public function studentStatement(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
        ]);

        $student = Student::with([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
            'currentEnrollment.academicYear',
        ])->findOrFail($request->integer('student_id'));

        $invoiceQuery = Invoice::allSchools()
            ->with(['payments', 'term'])
            ->where('student_id', $student->id)
            ->orderBy('due_date');

        if ($request->filled('academic_year_id')) {
            $invoiceQuery->where('academic_year_id', $request->integer('academic_year_id'));
        }

        $invoices = $invoiceQuery->get();

        $totalInvoiced = 0;
        $totalPaid = 0;

        $invoiceRows = $invoices->map(function (Invoice $inv) use (&$totalInvoiced, &$totalPaid) {
            $gross = $inv->total_amount_cents->cents()
                     + $inv->arrears_cents->cents()
                     - $inv->discount_cents->cents();
            $paid = $inv->paidCents();
            $balance = max(0, $gross - $paid);

            $totalInvoiced += $gross;
            $totalPaid += $paid;

            return [
                'invoice_number' => $inv->invoice_number,
                // Migrated invoices have no due_date (see debtorAging above).
                'due_date' => $inv->due_date?->toDateString(),
                'term' => $inv->term?->name,
                'gross_cents' => $gross,
                'paid_cents' => $paid,
                'balance_cents' => $balance,
                'status' => $inv->status,
                'payments' => $inv->payments->map(fn ($p) => [
                    'paid_at' => $p->paid_at->toDateTimeString(),
                    'amount_cents' => $p->amount_cents->cents(),
                    'method' => $p->method,
                    'reference_number' => $p->reference_number,
                ]),
            ];
        });

        $enrollment = $student->currentEnrollment;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'full_name' => $student->fullName(),
                'admission_number' => $enrollment?->admission_number,
                'school_class' => $enrollment?->schoolClass?->name,
                'school' => $enrollment?->school?->name,
                'academic_year' => $enrollment?->academicYear?->name,
            ],
            'invoices' => $invoiceRows,
            'total_invoiced_cents' => $totalInvoiced,
            'total_paid_cents' => $totalPaid,
            'balance_cents' => max(0, $totalInvoiced - $totalPaid),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  7 & 8. PDF / Excel exports
    // ─────────────────────────────────────────────────────────

    public function exportPdf(Request $request, string $type): Response
    {
        [$view, $data] = $this->resolveReportData($request, $type);

        $filename = $type.'_'.now()->format('Ymd_His').'.pdf';

        return $this->exporter->toPdf('pdf.reports.'.$view, $data, $filename);
    }

    public function exportExcel(Request $request, string $type): StreamedResponse
    {
        [$view, $data, $csvHeaders, $csvRows] = $this->resolveReportData($request, $type, true);

        $filename = $type.'_'.now()->format('Ymd_His').'.csv';

        return $this->exporter->toCsv($csvHeaders, $csvRows, $filename);
    }

    /**
     * Real .xlsx download for the Outstanding Debt dashboard card's printer icon —
     * the generic exportExcel() above only produces a .csv, and the user
     * specifically asked for an actual Excel file.
     */
    /**
     * Real .xlsx download for any Reports page tab — the "Export Excel" button
     * next to the Print button. Reuses the same headers/rows already built for
     * the CSV download instead of duplicating each report's shape again.
     */
    public function exportReportXlsx(Request $request, string $type): BinaryFileResponse
    {
        [, , $csvHeaders, $csvRows] = $this->resolveReportData($request, $type, true);

        $filename = $type.'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new GenericArrayExport($csvHeaders, $csvRows), $filename);
    }

    public function exportOutstandingDebtsXlsx(Request $request): BinaryFileResponse
    {
        $rows = $this->outstandingDebtsByStudent($request);
        $filename = 'outstanding_debts_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new OutstandingDebtsExport($rows), $filename);
    }

    /**
     * Every unpaid/partial invoice grouped per student: total debt owed and
     * the distinct list of terms still unpaid, with primary guardian contact
     * details attached. Shared by the CSV export and the real .xlsx export.
     *
     * @return array<int, array{student_name:string, guardian_name:string, guardian_phone:string, village_street:string, debt_cents:int, terms:array<int,string>}>
     */
    private function outstandingDebtsByStudent(Request $request): array
    {
        $schoolId = $this->activeSchoolId($request);
        $invoiceTable = (new Invoice)->getTable();
        $paymentTable = (new Payment)->getTable();

        $unpaidInvoices = Invoice::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->whereIn('status', ['unpaid', 'partial'])
            ->leftJoin(
                DB::raw("(SELECT invoice_id, SUM(amount_cents) as paid_sum FROM {$paymentTable} GROUP BY invoice_id) as p"),
                'p.invoice_id', '=', "{$invoiceTable}.id"
            )
            ->select("{$invoiceTable}.*", DB::raw("({$invoiceTable}.total_amount_cents - COALESCE(p.paid_sum, 0)) as balance_cents"))
            ->with(['student.guardians', 'student.currentEnrollment.schoolClass', 'term'])
            ->get();

        $byStudent = [];
        foreach ($unpaidInvoices as $inv) {
            $student = $inv->student;
            if (! $student) {
                continue;
            }
            $sid = $student->id;
            if (! isset($byStudent[$sid])) {
                $guardian = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
                $byStudent[$sid] = [
                    'student_name' => $student->fullName(),
                    'student_class' => $student->currentEnrollment?->schoolClass?->name ?? '',
                    'guardian_name' => $guardian ? trim($guardian->first_name.' '.$guardian->last_name) : '',
                    'guardian_phone' => $guardian->phone ?? '',
                    'village_street' => $student->street ?: $student->address ?: '',
                    'debt_cents' => 0,
                    'terms' => [],
                ];
            }
            $byStudent[$sid]['debt_cents'] += (int) $inv->balance_cents;
            if ($inv->term && ! in_array($inv->term->name, $byStudent[$sid]['terms'], true)) {
                $byStudent[$sid]['terms'][] = $inv->term->name;
            }
        }

        return array_values($byStudent);
    }

    // ─────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Resolve report data for PDF / Excel based on type string.
     * Returns [viewName, data, csvHeaders, csvRows] — csvHeaders/Rows only populated when $forCsv = true.
     */
    private function resolveReportData(Request $request, string $type, bool $forCsv = false): array
    {
        $view = '';
        $data = [];
        $csvHeaders = [];
        $csvRows = [];

        switch ($type) {
            case 'collections':
                $report = $this->collections($request)->getData(true);
                $view = 'collections';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Period', 'Collected (TZS)', 'Total Debt (TZS)', 'Total Partial Paid (TZS)', 'Payment Count'];
                    $csvRows = array_map(
                        fn ($r) => [
                            $r['period'],
                            round(($r['amount_cents'] ?? 0) / 100),
                            round(($r['total_debt_cents'] ?? 0) / 100),
                            round(($r['total_partial_paid_cents'] ?? 0) / 100),
                            $r['payment_count'],
                        ],
                        $report['rows']
                    );

                    $discountLabels = ['sibling' => 'Sibling Discount', 'staff' => 'Staff Discount', 'sponsor' => 'Sponsor Discount', 'other' => 'Other Discount'];
                    $sponsorshipLabels = ['half' => 'Half Sponsored', 'full' => 'Fully Sponsored (Free)', 'full_paid' => 'Fully Sponsored (Paid via Sponsor)'];

                    $csvRows[] = ['', '', '', '', ''];
                    $csvRows[] = ['DISCOUNTS BY TYPE', 'Students', 'Amount (TZS)', '', ''];
                    foreach ($report['by_discount_type'] ?? [] as $d) {
                        $csvRows[] = [$discountLabels[$d['type']] ?? $d['type'], $d['count'], round($d['amount_cents'] / 100), '', ''];
                    }

                    $csvRows[] = ['', '', '', '', ''];
                    $csvRows[] = ['SPONSORSHIPS BY TYPE', 'Students', 'Amount Collected (TZS)', '', ''];
                    foreach ($report['by_sponsorship_type'] ?? [] as $s) {
                        $csvRows[] = [$sponsorshipLabels[$s['type']] ?? $s['type'], $s['count'], round($s['amount_cents'] / 100), '', ''];
                    }
                }
                break;

            case 'by-class':
                $report = $this->byClass($request)->getData(true);
                $view = 'by_class';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Class', 'Students', 'Billed (TZS)', 'Collected (TZS)', 'Outstanding (TZS)', 'Paid Invoices', 'Partial Invoices', 'Unpaid Invoices'];
                    $csvRows = array_map(
                        fn ($r) => [
                            $r['class_name'],
                            $r['student_count'],
                            round($r['total_billed_cents'] / 100),
                            round($r['total_collected_cents'] / 100),
                            round($r['total_outstanding_cents'] / 100),
                            $r['paid_count'],
                            $r['partial_count'],
                            $r['unpaid_count'],
                        ],
                        $report['rows']
                    );
                }
                break;

            case 'vs':
                $report = $this->expensesVsCollections($request)->getData(true);
                $view = 'expenses_vs_collections';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                    $csvHeaders = ['Month', 'Collections (TZS)', 'Expenses (TZS)', 'Net (TZS)'];
                    $csvRows = array_map(
                        fn ($r) => [
                            $months[$r['month']] ?? $r['month'],
                            round($r['collections_cents'] / 100),
                            round($r['expenses_cents'] / 100),
                            round($r['net_cents'] / 100),
                        ],
                        $report['rows']
                    );
                }
                break;

            case 'debtor-aging':
                $report = $this->debtorAging($request)->getData(true);
                $view = 'debtor_aging';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Full Name', 'Admission No.', 'Class', 'Guardian Name', 'Guardian Phone', 'Village/Street', 'Oldest Invoice Date', 'Days Overdue', 'Outstanding (TZS)', 'Terms Not Paid', 'Bucket'];
                    foreach ($report['buckets'] as $bucket => $info) {
                        foreach ($info['students'] as $s) {
                            $csvRows[] = [
                                $s['full_name'],
                                $s['admission_number'],
                                $s['school_class'],
                                $s['guardian_name'],
                                $s['guardian_phone'] !== '—' ? "'".$s['guardian_phone'] : $s['guardian_phone'],
                                $s['village_street'],
                                $s['oldest_invoice_date'],
                                $s['oldest_age'],
                                round($s['outstanding_cents'] / 100),
                                $s['terms_not_paid'],
                                $bucket,
                            ];
                        }
                    }
                }
                break;

            case 'outstanding-debts':
                $view = 'outstanding_debts';
                $school = $this->resolveSchool($request);
                $byStudent = $this->outstandingDebtsByStudent($request);

                $report = ['rows' => array_values($byStudent)];
                $data = compact('report', 'school');

                if ($forCsv) {
                    $csvHeaders = ['Student Name', 'Class', 'Parent/Guardian Name', 'Parent Phone', 'Village/Street', 'Debt Amount (TZS)', 'Terms Not Paid'];
                    foreach ($byStudent as $r) {
                        $csvRows[] = [
                            $r['student_name'],
                            $r['student_class'],
                            $r['guardian_name'],
                            // Leading apostrophe forces Excel/Sheets to keep this as text
                            // instead of rendering a long digit string in scientific notation.
                            $r['guardian_phone'] !== '' ? "'".$r['guardian_phone'] : '',
                            $r['village_street'],
                            round($r['debt_cents'] / 100),
                            implode(', ', $r['terms']),
                        ];
                    }
                }
                break;

            case 'income-statement':
                $report = $this->incomeStatement($request)->getData(true);
                $view = 'income_statement';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Line Item', 'Amount (cents)'];
                    $csvRows[] = ['Fee Collections', $report['revenue']['fee_collections']];
                    $csvRows[] = ['Total Revenue', $report['revenue']['total']];
                    foreach ($report['expenses']['by_category'] as $cat) {
                        $csvRows[] = ['Expense: '.$cat['category'], $cat['amount_cents']];
                    }
                    $csvRows[] = ['Payroll', $report['expenses']['payroll']];
                    $csvRows[] = ['Total Expenses', $report['expenses']['total']];
                    $csvRows[] = ['Net Income', $report['net_income_cents']];
                }
                break;

            case 'balance-sheet':
                $report = $this->balanceSheet($request)->getData(true);
                $view = 'balance_sheet';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Section', 'Line Item', 'Amount (cents)'];
                    $csvRows[] = ['Assets', 'Cash & Bank', $report['assets']['cash_and_bank']['amount_cents']];
                    $csvRows[] = ['Assets', 'Receivables', $report['assets']['receivables']['amount_cents']];
                    $csvRows[] = ['Assets', 'Fixed Assets', $report['assets']['fixed_assets']['amount_cents']];
                    $csvRows[] = ['Assets', 'Total', $report['assets']['total']];
                    $csvRows[] = ['Liabilities', 'Payables', $report['liabilities']['payables']['amount_cents']];
                    $csvRows[] = ['Liabilities', 'Total', $report['liabilities']['total']];
                    $csvRows[] = ['Equity', 'Retained', $report['equity']['retained']];
                    $csvRows[] = ['Equity', 'Total', $report['equity']['total']];
                }
                break;

            case 'cash-flow':
                $report = $this->cashFlow($request)->getData(true);
                $view = 'cash_flow';
                $school = $this->resolveSchool($request);
                $data = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Section', 'Line Item', 'Amount (cents)'];
                    $csvRows[] = ['Operating', 'Fee Collections', $report['operating']['fee_collections']];
                    $csvRows[] = ['Operating', 'Expense Payments', $report['operating']['expense_payments']];
                    $csvRows[] = ['Operating', 'Payroll Payments', $report['operating']['payroll_payments']];
                    $csvRows[] = ['Operating', 'Supplier Payments', $report['operating']['supplier_payments']];
                    $csvRows[] = ['Operating', 'Net', $report['operating']['net']];
                    $csvRows[] = ['Investing', 'Asset Purchases', $report['investing']['asset_purchases']];
                    $csvRows[] = ['Investing', 'Net', $report['investing']['net']];
                    $csvRows[] = ['Total', 'Net Change', $report['net_change_cents']];
                }
                break;

            case 'student-statement':
                $report = $this->studentStatement($request)->getData(true);
                $view = 'student_statement';
                $data = ['report' => $report];
                if ($forCsv) {
                    $csvHeaders = ['Invoice No.', 'Due Date', 'Term', 'Gross (cents)', 'Paid (cents)', 'Balance (cents)', 'Status'];
                    foreach ($report['invoices'] as $inv) {
                        $csvRows[] = [
                            $inv['invoice_number'],
                            $inv['due_date'],
                            $inv['term'],
                            $inv['gross_cents'],
                            $inv['paid_cents'],
                            $inv['balance_cents'],
                            $inv['status'],
                        ];
                    }
                }
                break;

            default:
                abort(404, "Unknown report type: {$type}");
        }

        return [$view, $data, $csvHeaders, $csvRows];
    }

    private function resolveSchool(Request $request): ?School
    {
        if ($request->filled('school_id')) {
            return School::find($request->integer('school_id'));
        }
        if (app()->bound('active_school')) {
            return app('active_school');
        }

        return null;
    }
}
