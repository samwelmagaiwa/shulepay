<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\School;
use App\Models\Student;
use App\Models\SupplierPayment;
use App\Services\Reporting\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private readonly ReportExportService $exporter) {}

    // ─────────────────────────────────────────────────────────
    //  1. Collections
    // ─────────────────────────────────────────────────────────

    public function collections(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'group_by'  => 'nullable|in:day,week,month,class',
        ]);

        $from    = $request->date('from', today()->startOfMonth());
        $to      = $request->date('to', today());
        $groupBy = $request->input('group_by', 'day');

        // Base payment query (bypass school scope if no school bound)
        $base = Payment::allSchools()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($request->filled('school_id'), fn ($q) => $q->where('payments.school_id', $request->integer('school_id')));

        // Summary totals
        $totalPayments = (clone $base)->count();
        $totalAmount   = (clone $base)->sum('amount_cents');

        // Invoice counts by status for the period
        $invoiceBase = Invoice::allSchools()
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')));

        $invoiceCount  = (clone $invoiceBase)->count();
        $paidCount     = (clone $invoiceBase)->where('status', 'paid')->count();
        $partialCount  = (clone $invoiceBase)->where('status', 'partial')->count();
        $unpaidCount   = (clone $invoiceBase)->where('status', 'unpaid')->count();

        // $groupBy is validated above as in:day,week,month,class — safe to use in DB::raw
        $periodExpr = match ($groupBy) {
            'week'  => DB::raw("DATE_FORMAT(paid_at, '%x-W%v') as period"),
            'month' => DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as period"),
            default => DB::raw("DATE(paid_at) as period"),  // day & class both row by day
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
                'period'        => $r->period,
                'amount_cents'  => (int) $r->amount_cents,
                'payment_count' => (int) $r->payment_count,
            ])
            ->toArray();

        // By payment method
        $byMethod = (clone $base)
            ->select('method', DB::raw('SUM(amount_cents) as amount_cents'), DB::raw('COUNT(*) as count'))
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method'       => $r->method,
                'amount_cents' => (int) $r->amount_cents,
                'count'        => (int) $r->count,
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
            ->when($request->filled('school_id'), fn ($q) => $q->where('payments.school_id', $request->integer('school_id')))
            ->select('school_classes.name as class', DB::raw('SUM(payments.amount_cents) as amount_cents'))
            ->groupBy('school_classes.name')
            ->orderBy('school_classes.name')
            ->get()
            ->map(fn ($r) => [
                'class'        => $r->class,
                'amount_cents' => (int) $r->amount_cents,
            ])
            ->toArray();

        $data = [
            'summary' => [
                'total_payments'      => $totalPayments,
                'total_amount_cents'  => (int) $totalAmount,
                'invoice_count'       => $invoiceCount,
                'paid_count'          => $paidCount,
                'partial_count'       => $partialCount,
                'unpaid_count'        => $unpaidCount,
            ],
            'rows'      => $rows,
            'by_method' => $byMethod,
            'by_class'  => $byClass,
            'period'    => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
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
            'as_of'     => 'nullable|date',
        ]);

        $asOf = $request->date('as_of', today());

        // Fetch unpaid / partial invoices with student info
        $invoices = Invoice::allSchools()
            ->with(['student.currentEnrollment.schoolClass'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->get();

        // Group by student, track oldest invoice due_date per student
        $studentMap = [];

        foreach ($invoices as $inv) {
            $sid  = $inv->student_id;
            $balance = $inv->balanceDueCents();

            if ($balance <= 0) {
                continue;
            }

            $age = (int) $inv->due_date->diffInDays($asOf, false);
            // Negative age means not yet due — treat as current (0)
            $age = max(0, $age);

            if (!isset($studentMap[$sid])) {
                $enrollment = $inv->student?->currentEnrollment;
                $studentMap[$sid] = [
                    'id'                   => $inv->student_id,
                    'full_name'            => $inv->student?->fullName() ?? '—',
                    'admission_number'     => $enrollment?->admission_number ?? '—',
                    'school_class'         => $enrollment?->schoolClass?->name ?? '—',
                    'oldest_invoice_date'  => $inv->due_date->toDateString(),
                    'oldest_age'           => $age,
                    'outstanding_cents'    => $balance,
                ];
            } else {
                $studentMap[$sid]['outstanding_cents'] += $balance;
                if ($age > $studentMap[$sid]['oldest_age']) {
                    $studentMap[$sid]['oldest_age']         = $age;
                    $studentMap[$sid]['oldest_invoice_date'] = $inv->due_date->toDateString();
                }
            }
        }

        $buckets = [
            'current'    => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_1_30'  => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_31_60' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'days_61_90' => ['count' => 0, 'amount_cents' => 0, 'students' => []],
            'over_90'    => ['count' => 0, 'amount_cents' => 0, 'students' => []],
        ];

        foreach ($studentMap as $s) {
            $age    = $s['oldest_age'];
            $bucket = match (true) {
                $age <= 0  => 'current',
                $age <= 30 => 'days_1_30',
                $age <= 60 => 'days_31_60',
                $age <= 90 => 'days_61_90',
                default    => 'over_90',
            };

            $record = [
                'id'                  => $s['id'],
                'full_name'           => $s['full_name'],
                'admission_number'    => $s['admission_number'],
                'school_class'        => $s['school_class'],
                'oldest_invoice_date' => $s['oldest_invoice_date'],
                'outstanding_cents'   => $s['outstanding_cents'],
            ];

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount_cents'] += $s['outstanding_cents'];
            $buckets[$bucket]['students'][]    = $record;
        }

        $totalDebtors     = count($studentMap);
        $totalOutstanding = array_sum(array_column($studentMap, 'outstanding_cents'));

        return response()->json([
            'summary' => [
                'total_debtors'          => $totalDebtors,
                'total_outstanding_cents' => $totalOutstanding,
                'as_of'                  => $asOf->toDateString(),
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
            'school_id'        => 'nullable|integer|exists:schools,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'from'             => 'nullable|date',
            'to'               => 'nullable|date',
        ]);

        $from = $request->date('from', today()->startOfYear());
        $to   = $request->date('to', today());

        $schoolFilter = fn ($q) => $q->when(
            $request->filled('school_id'),
            fn ($q) => $q->where('school_id', $request->integer('school_id'))
        );

        // Revenue: fee collections (payments) in period
        $feeCollections = Payment::allSchools()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->sum('amount_cents');

        $totalRevenue = (int) $feeCollections;

        // Expenses: approved expenses in period
        $expensesByCategory = Expense::allSchools()
            ->with('category')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->select('category_id', DB::raw('SUM(amount_cents) as amount_cents'))
            ->groupBy('category_id')
            ->get()
            ->map(fn ($r) => [
                'category'     => $r->category?->name ?? 'Uncategorised',
                'amount_cents' => (int) $r->amount_cents,
            ])
            ->toArray();

        $totalExpenses = array_sum(array_column($expensesByCategory, 'amount_cents'));

        // Payroll: paid payroll in period
        $payrollTotal = Payroll::allSchools()
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->sum('net_salary_cents');

        $payrollTotal  = (int) $payrollTotal;
        $totalExpenses += $payrollTotal;

        return response()->json([
            'revenue' => [
                'fee_collections' => (int) $feeCollections,
                'total'           => $totalRevenue,
            ],
            'expenses' => [
                'by_category' => $expensesByCategory,
                'payroll'     => $payrollTotal,
                'total'       => $totalExpenses,
            ],
            'net_income_cents' => $totalRevenue - $totalExpenses,
            'period'           => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  4. Balance Sheet
    // ─────────────────────────────────────────────────────────

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'as_of'     => 'nullable|date',
        ]);

        $asOf = $request->date('as_of', today());

        $schoolId = $request->integer('school_id') ?: null;

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
        $approvedExpenses  = Expense::allSchools()
            ->where('status', 'approved')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $supplierPaymentsTotal = SupplierPayment::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount_cents');

        $payables    = max(0, (int) $approvedExpenses - (int) $supplierPaymentsTotal);
        $totalLiab   = $payables;

        // Equity = Assets - Liabilities
        $retained    = $totalAssets - $totalLiab;
        $totalEquity = $retained;

        return response()->json([
            'assets' => [
                'cash_and_bank' => [
                    'description'  => 'Received fees (payments total)',
                    'amount_cents' => (int) $cashAndBank,
                ],
                'receivables' => [
                    'description'  => 'Outstanding invoices',
                    'amount_cents' => (int) $receivables,
                ],
                'fixed_assets' => [
                    'description'  => 'Asset register book value',
                    'amount_cents' => (int) $fixedAssets,
                ],
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'payables' => [
                    'description'  => 'Supplier balances owed',
                    'amount_cents' => $payables,
                ],
                'total' => $totalLiab,
            ],
            'equity' => [
                'retained' => $retained,
                'total'    => $totalEquity,
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
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
        ]);

        $from     = $request->date('from', today()->startOfMonth());
        $to       = $request->date('to', today());
        $schoolId = $request->integer('school_id') ?: null;

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

        $feeCollections   = (int) $feeCollections;
        $expensePayments  = (int) $expensePayments;
        $payrollPayments  = (int) $payrollPayments;
        $supplierPayments = (int) $supplierPayments;

        $operatingNet = $feeCollections - $expensePayments - $payrollPayments - $supplierPayments;

        // Investing: asset purchases in period
        $assetPurchases = Asset::allSchools()
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('cost_cents');

        $assetPurchases = (int) $assetPurchases;
        $investingNet   = -$assetPurchases;

        return response()->json([
            'operating' => [
                'fee_collections'  => $feeCollections,
                'expense_payments' => $expensePayments,
                'payroll_payments' => $payrollPayments,
                'supplier_payments'=> $supplierPayments,
                'net'              => $operatingNet,
            ],
            'investing' => [
                'asset_purchases' => $assetPurchases,
                'net'             => $investingNet,
            ],
            'net_change_cents' => $operatingNet + $investingNet,
            'period'           => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  6. Student Statement
    // ─────────────────────────────────────────────────────────

    public function studentStatement(Request $request): JsonResponse
    {
        $request->validate([
            'student_id'       => 'required|integer|exists:students,id',
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
        $totalPaid     = 0;

        $invoiceRows = $invoices->map(function (Invoice $inv) use (&$totalInvoiced, &$totalPaid) {
            $gross   = $inv->total_amount_cents->cents()
                     + $inv->arrears_cents->cents()
                     - $inv->discount_cents->cents();
            $paid    = $inv->paidCents();
            $balance = max(0, $gross - $paid);

            $totalInvoiced += $gross;
            $totalPaid     += $paid;

            return [
                'invoice_number'       => $inv->invoice_number,
                'due_date'             => $inv->due_date->toDateString(),
                'term'                 => $inv->term?->name,
                'gross_cents'          => $gross,
                'paid_cents'           => $paid,
                'balance_cents'        => $balance,
                'status'               => $inv->status,
                'payments'             => $inv->payments->map(fn ($p) => [
                    'paid_at'          => $p->paid_at->toDateTimeString(),
                    'amount_cents'     => $p->amount_cents->cents(),
                    'method'           => $p->method,
                    'reference_number' => $p->reference_number,
                ]),
            ];
        });

        $enrollment = $student->currentEnrollment;

        return response()->json([
            'student' => [
                'id'               => $student->id,
                'full_name'        => $student->fullName(),
                'admission_number' => $enrollment?->admission_number,
                'school_class'     => $enrollment?->schoolClass?->name,
                'school'           => $enrollment?->school?->name,
                'academic_year'    => $enrollment?->academicYear?->name,
            ],
            'invoices'         => $invoiceRows,
            'total_invoiced_cents' => $totalInvoiced,
            'total_paid_cents'     => $totalPaid,
            'balance_cents'        => max(0, $totalInvoiced - $totalPaid),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  7 & 8. PDF / Excel exports
    // ─────────────────────────────────────────────────────────

    public function exportPdf(Request $request, string $type): \Symfony\Component\HttpFoundation\Response
    {
        [$view, $data] = $this->resolveReportData($request, $type);

        $filename = $type . '_' . now()->format('Ymd_His') . '.pdf';

        return $this->exporter->toPdf('pdf.reports.' . $view, $data, $filename);
    }

    public function exportExcel(Request $request, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$view, $data, $csvHeaders, $csvRows] = $this->resolveReportData($request, $type, true);

        $filename = $type . '_' . now()->format('Ymd_His') . '.csv';

        return $this->exporter->toCsv($csvHeaders, $csvRows, $filename);
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
        $view        = '';
        $data        = [];
        $csvHeaders  = [];
        $csvRows     = [];

        switch ($type) {
            case 'collections':
                $report     = $this->collections($request)->getData(true);
                $view       = 'collections';
                $school     = $this->resolveSchool($request);
                $data       = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Period', 'Amount (cents)', 'Payment Count'];
                    $csvRows    = array_map(
                        fn ($r) => [$r['period'], $r['amount_cents'], $r['payment_count']],
                        $report['rows']
                    );
                }
                break;

            case 'debtor-aging':
                $report     = $this->debtorAging($request)->getData(true);
                $view       = 'debtor_aging';
                $school     = $this->resolveSchool($request);
                $data       = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Full Name', 'Admission No.', 'Class', 'Oldest Invoice Date', 'Outstanding (cents)', 'Bucket'];
                    foreach ($report['buckets'] as $bucket => $info) {
                        foreach ($info['students'] as $s) {
                            $csvRows[] = [
                                $s['full_name'],
                                $s['admission_number'],
                                $s['school_class'],
                                $s['oldest_invoice_date'],
                                $s['outstanding_cents'],
                                $bucket,
                            ];
                        }
                    }
                }
                break;

            case 'income-statement':
                $report     = $this->incomeStatement($request)->getData(true);
                $view       = 'income_statement';
                $school     = $this->resolveSchool($request);
                $data       = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Line Item', 'Amount (cents)'];
                    $csvRows[]  = ['Fee Collections', $report['revenue']['fee_collections']];
                    $csvRows[]  = ['Total Revenue', $report['revenue']['total']];
                    foreach ($report['expenses']['by_category'] as $cat) {
                        $csvRows[] = ['Expense: ' . $cat['category'], $cat['amount_cents']];
                    }
                    $csvRows[]  = ['Payroll', $report['expenses']['payroll']];
                    $csvRows[]  = ['Total Expenses', $report['expenses']['total']];
                    $csvRows[]  = ['Net Income', $report['net_income_cents']];
                }
                break;

            case 'balance-sheet':
                $report     = $this->balanceSheet($request)->getData(true);
                $view       = 'balance_sheet';
                $school     = $this->resolveSchool($request);
                $data       = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Section', 'Line Item', 'Amount (cents)'];
                    $csvRows[]  = ['Assets', 'Cash & Bank', $report['assets']['cash_and_bank']['amount_cents']];
                    $csvRows[]  = ['Assets', 'Receivables', $report['assets']['receivables']['amount_cents']];
                    $csvRows[]  = ['Assets', 'Fixed Assets', $report['assets']['fixed_assets']['amount_cents']];
                    $csvRows[]  = ['Assets', 'Total', $report['assets']['total']];
                    $csvRows[]  = ['Liabilities', 'Payables', $report['liabilities']['payables']['amount_cents']];
                    $csvRows[]  = ['Liabilities', 'Total', $report['liabilities']['total']];
                    $csvRows[]  = ['Equity', 'Retained', $report['equity']['retained']];
                    $csvRows[]  = ['Equity', 'Total', $report['equity']['total']];
                }
                break;

            case 'cash-flow':
                $report     = $this->cashFlow($request)->getData(true);
                $view       = 'cash_flow';
                $school     = $this->resolveSchool($request);
                $data       = compact('report', 'school');
                if ($forCsv) {
                    $csvHeaders = ['Section', 'Line Item', 'Amount (cents)'];
                    $csvRows[]  = ['Operating', 'Fee Collections', $report['operating']['fee_collections']];
                    $csvRows[]  = ['Operating', 'Expense Payments', $report['operating']['expense_payments']];
                    $csvRows[]  = ['Operating', 'Payroll Payments', $report['operating']['payroll_payments']];
                    $csvRows[]  = ['Operating', 'Supplier Payments', $report['operating']['supplier_payments']];
                    $csvRows[]  = ['Operating', 'Net', $report['operating']['net']];
                    $csvRows[]  = ['Investing', 'Asset Purchases', $report['investing']['asset_purchases']];
                    $csvRows[]  = ['Investing', 'Net', $report['investing']['net']];
                    $csvRows[]  = ['Total', 'Net Change', $report['net_change_cents']];
                }
                break;

            case 'student-statement':
                $report     = $this->studentStatement($request)->getData(true);
                $view       = 'student_statement';
                $data       = ['report' => $report];
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
