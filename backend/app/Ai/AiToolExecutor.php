<?php

namespace App\Ai;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Budget;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeStructure;
use App\Models\GoodsReceipt;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PettyCash;
use App\Models\Refund;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\Term;
use App\Models\User;
use App\Services\Budget\BudgetActuals;
use App\Services\Exam\ExamAnalysisService;
use App\Services\Exam\ExamCommentService;
use App\Services\Exam\ExamProgressService;
use App\Services\Exam\ExamResultsService;
use App\Services\Exam\ExamScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs the read-only data lookups the assistant asks for. Every query goes
 * through the normal models, so the BelongsToSchool scope keeps the answers
 * inside the school(s) the signed-in user is working in.
 */
class AiToolExecutor
{
    public function __construct(private User $user) {}

    public function execute(string $name, array $args): array
    {
        try {
            return match ($name) {
                'get_payment_summary' => $this->paymentSummary($args),
                'get_unpaid_invoices' => $this->unpaidInvoices($args),
                'get_invoice_stats' => $this->invoiceStats($args),
                'get_expense_summary' => $this->expenseSummary($args),
                'get_expenses_list' => $this->expensesList($args),
                'get_petty_cash_summary' => $this->pettyCashSummary($args),
                'get_recent_payments' => $this->recentPayments($args),
                'get_fee_structures' => $this->feeStructures($args),
                'get_student_stats' => $this->studentStats($args),
                'get_class_distribution' => $this->classDistribution($args),
                'get_student_list' => $this->studentList($args),
                'get_new_enrollments' => $this->newEnrollments($args),
                'get_academic_years' => $this->academicYears(),
                'get_exam_list' => $this->examList($args),
                'get_exam_results_summary' => $this->examResultsSummary($args),
                'get_top_students' => $this->topStudents($args),
                'get_subject_averages' => $this->subjectAverages($args),
                'get_exam_progress' => $this->examProgress($args),
                'get_exam_analysis' => $this->examAnalysis($args),
                'get_exam_comments_status' => $this->examCommentsStatus($args),
                'get_student_exam_history' => $this->studentExamHistory($args),
                'get_supplier_outstanding' => $this->supplierOutstanding($args),
                'get_supplier_spend' => $this->supplierSpend($args),
                'get_recent_purchases' => $this->recentPurchases($args),
                'get_payroll_summary' => $this->payrollSummary($args),
                'get_employee_list' => $this->employeeList($args),
                'get_school_overview' => $this->schoolOverview(),
                'get_class_list' => $this->classList($args),
                'get_current_term' => $this->currentTerm(),
                'get_subject_list' => $this->subjectList(),
                'get_attendance_summary' => $this->attendanceSummary($args),
                'get_student_attendance' => $this->studentAttendance($args),
                'get_attendance_absentees' => $this->attendanceAbsentees($args),
                'get_low_attendance_students' => $this->lowAttendanceStudents($args),
                'get_revenue_trend' => $this->revenueTrend($args),
                'get_budget_summary' => $this->budgetSummary($args),
                'get_budget_forecast' => $this->budgetForecast($args),
                'get_budget_history' => $this->budgetHistory($args),
                'get_discount_summary' => $this->discountSummary($args),
                'get_refund_summary' => $this->refundSummary($args),
                'get_student_financial_summary' => $this->studentFinancialSummary($args),
                'get_guardian_list' => $this->guardianList($args),
                default => ['error' => "Unknown tool: {$name}"],
            };
        } catch (\Throwable $e) {
            Log::error("AI tool [{$name}] failed", [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return ['error' => 'Could not retrieve data. Please try again.'];
        }
    }

    /** @return list<int> the schools an answer may cover; empty means every school (superadmin only). */
    private function schoolIds(): array
    {
        if (app()->bound('active_school') && app('active_school')) {
            return [app('active_school')->id];
        }
        if ($this->user->isSuperAdmin()) {
            return [];
        }

        // A user tied to no school sees nothing rather than everything.
        return $this->user->allAccessibleSchoolIds() ?: [0];
    }

    /**
     * Limit a query to those schools. Done explicitly on every query: several models here
     * (students, enrollments, classes, years, attendance, refunds...) carry no automatic
     * school scope, and the automatic one disappears in "all schools" mode.
     */
    private function inSchools($query, string $column = 'school_id')
    {
        $ids = $this->schoolIds();

        return $ids ? $query->whereIn($column, $ids) : $query;
    }

    private function students()
    {
        return Student::whereHas('enrollments', fn ($e) => $this->inSchools($e));
    }

    private function guardians()
    {
        return Guardian::whereHas('students', fn ($s) => $s->whereHas('enrollments', fn ($e) => $this->inSchools($e)));
    }

    private function terms()
    {
        return Term::whereHas('academicYear', fn ($y) => $this->inSchools($y));
    }

    private function money(int|float $cents): string
    {
        return 'TZS '.number_format($cents / 100);
    }

    /** Escape LIKE wildcards so a typed "%" is matched literally. */
    private function like(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }

    private function fullName(?object $p): string
    {
        return $p ? trim($p->first_name.' '.$p->last_name) : '';
    }

    private function statusValue(mixed $status): mixed
    {
        return $status instanceof \BackedEnum ? $status->value : $status;
    }

    /** Every word typed must match the first or last name, so "juma ali" finds "Juma Ali". */
    private function findStudents(string $name, int $limit = 5)
    {
        $words = array_filter(preg_split('/\s+/', trim($name)));
        if ($words === []) {
            return collect();
        }

        return $this->students()->where(function ($q) use ($words) {
            foreach ($words as $w) {
                $q->where(fn ($n) => $n->where('first_name', 'like', $this->like($w))->orWhere('last_name', 'like', $this->like($w)));
            }
        })->limit($limit)->get();
    }

    // ─── Finance ────────────────────────────────────────────────────────────────

    private function paymentSummary(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();
        $range = [$from, $to.' 23:59:59'];

        $total = $this->inSchools(Payment::query())->whereBetween('paid_at', $range)->sum('amount_cents');
        $count = $this->inSchools(Payment::query())->whereBetween('paid_at', $range)->count();

        $byMethod = $this->inSchools(Payment::query())->whereBetween('paid_at', $range)
            ->selectRaw('method, SUM(amount_cents) as total, COUNT(*) as count')
            ->groupBy('method')
            ->get()
            ->map(fn ($r) => [
                'method' => $this->statusValue($r->method),
                'total' => $this->money($r->total),
                'count' => $r->count,
            ])->values()->toArray();

        return [
            'period' => "{$from} to {$to}",
            'total_collected' => $this->money($total),
            'transaction_count' => $count,
            'by_method' => $byMethod,
        ];
    }

    private function unpaidInvoices(array $a): array
    {
        $statuses = match ($a['status'] ?? 'both') {
            'unpaid' => ['unpaid'],
            'partial' => ['partial'],
            default => ['unpaid', 'partial'],
        };

        $q = $this->inSchools(Invoice::query())->with(['student', 'student.currentEnrollment.schoolClass', 'payments'])
            ->whereIn('status', $statuses)
            ->latest();

        if (! empty($a['class_name'])) {
            $q->whereHas('student.currentEnrollment.schoolClass', fn ($c) => $c->where('name', 'like', $this->like($a['class_name'])));
        }

        $rows = $q->limit(min(100, (int) ($a['limit'] ?? 20)))->get()->map(fn ($inv) => [
            'student' => $this->fullName($inv->student),
            'class' => $inv->student?->currentEnrollment?->schoolClass?->name,
            'balance' => $this->money($inv->balanceDueCents()),
            'status' => $this->statusValue($inv->status),
            'due_date' => optional($inv->due_date)->format('d M Y'),
        ])->toArray();

        return ['count' => count($rows), 'invoices' => $rows];
    }

    private function invoiceStats(array $a): array
    {
        $q = $this->inSchools(Invoice::query());
        if (! empty($a['term_id'])) {
            $q->where('term_id', $a['term_id']);
        }
        if (! empty($a['academic_year_id'])) {
            $q->where('academic_year_id', $a['academic_year_id']);
        }

        // Clone: first() adds LIMIT 1 to the builder, and $q is reused below as the invoice subquery.
        $stats = (clone $q)->selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
             SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
             SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
             SUM(total_amount_cents + arrears_cents - discount_cents) as total_invoiced"
        )->first();

        $paid = $this->inSchools(Payment::query())->whereIn('invoice_id', (clone $q)->select('id'))->sum('amount_cents');
        $invoiced = (int) ($stats->total_invoiced ?? 0);

        return [
            'total_invoices' => (int) $stats->total,
            'paid' => (int) $stats->paid_count,
            'unpaid' => (int) $stats->unpaid_count,
            'partial' => (int) $stats->partial_count,
            'total_invoiced' => $this->money($invoiced),
            'total_collected' => $this->money($paid),
            'total_outstanding' => $this->money(max(0, $invoiced - $paid)),
        ];
    }

    private function expenseSummary(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();

        $money = fn ($cents) => 'TZS '.number_format($cents / 100);
        $scope = fn () => $this->inSchools(Expense::query())->whereDate('expense_date', '>=', $from)->whereDate('expense_date', '<=', $to)
            ->when(! empty($a['vendor']), fn ($q) => $q->where('vendor', 'like', '%'.$a['vendor'].'%'))
            ->when(! empty($a['category_name']), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', '%'.$a['category_name'].'%')));

        $by = $scope()->selectRaw('status, payment_mode, COUNT(*) AS n, SUM(amount_cents) AS cents')->groupBy('status', 'payment_mode')->get();
        $approved = $by->where('status', 'approved');
        $pending = $by->where('status', 'pending');

        $group = in_array($a['group_by'] ?? 'category', ['category', 'vendor', 'payment_mode', 'month'], true) ? ($a['group_by'] ?? 'category') : 'category';
        $key = match ($group) {
            'vendor' => "COALESCE(NULLIF(vendor, ''), 'No vendor')",
            'payment_mode' => 'payment_mode',
            'month' => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', expense_date)" : "DATE_FORMAT(expense_date, '%Y-%m')",
            default => 'category_id',
        };
        $rows = $scope()->where('status', 'approved')->selectRaw("$key AS k, SUM(amount_cents) AS cents, COUNT(*) AS n")->groupBy('k')->orderByDesc('cents')->limit(25)->get();
        $names = $group === 'category' ? ExpenseCategory::whereIn('id', $rows->pluck('k'))->pluck('name', 'id') : collect();
        $breakdown = $rows->map(fn ($r) => [
            $group => match ($group) {
                'category' => $names[$r->k] ?? 'Uncategorised',
                'payment_mode' => $r->k === 'credit' ? 'credit (still owed)' : 'paid',
                default => $r->k,
            },
            'total' => $money($r->cents),
            'count' => (int) $r->n,
        ])->values()->toArray();

        return [
            'period' => "{$from} to {$to}",
            'note' => 'Only approved expenses count as spent. Credit purchases are approved spending that is still owed to suppliers.',
            'grand_total' => $money($approved->sum('cents')),
            'paid_now' => $money($approved->where('payment_mode', '!=', 'credit')->sum('cents')),
            'on_credit_owed_to_suppliers' => $money($approved->where('payment_mode', 'credit')->sum('cents')),
            'approved_count' => (int) $approved->sum('n'),
            'waiting_for_approval' => ['count' => (int) $pending->sum('n'), 'total' => $money($pending->sum('cents'))],
            'rejected_count' => (int) $by->where('status', 'rejected')->sum('n'),
            'grouped_by' => $group,
            'breakdown' => $breakdown,
            'by_category' => $group === 'category' ? $breakdown : [],
        ];
    }

    private function expensesList(array $a): array
    {
        $limit = max(1, min(50, (int) ($a['limit'] ?? 15)));
        $q = $this->inSchools(Expense::query())->with(['category:id,name', 'recorder:id,name', 'approver:id,name'])
            ->when(! empty($a['status']), fn ($q) => $q->where('status', $a['status']))
            ->when(! empty($a['payment_mode']), fn ($q) => $q->where('payment_mode', $a['payment_mode'] === 'credit' ? 'credit' : 'paid'))
            ->when(! empty($a['from_date']), fn ($q) => $q->whereDate('expense_date', '>=', $a['from_date']))
            ->when(! empty($a['to_date']), fn ($q) => $q->whereDate('expense_date', '<=', $a['to_date']))
            ->when(! empty($a['search']), function ($q) use ($a) {
                $t = '%'.addcslashes($a['search'], '%_\\').'%';
                $q->where(fn ($w) => $w->where('description', 'like', $t)->orWhere('vendor', 'like', $t)->orWhere('receipt_reference', 'like', $t)->orWhere('notes', 'like', $t));
            });
        $total = (clone $q)->count();
        $sum = (clone $q)->sum('amount_cents');
        $rows = $q->latest('expense_date')->latest('id')->limit($limit)->get();

        return [
            'matching' => $total,
            'shown' => $rows->count(),
            'total_of_matching' => 'TZS '.number_format($sum / 100),
            'expenses' => $rows->map(fn ($e) => array_filter([
                'date' => $e->expense_date?->toDateString(),
                'description' => $e->description,
                'amount' => 'TZS '.number_format($e->amount_cents->tzs()),
                'category' => $e->category?->name,
                'vendor' => $e->vendor,
                'receipt_reference' => $e->receipt_reference,
                'payment' => $e->payment_mode === 'credit' ? 'on credit' : 'paid',
                'status' => $e->status,
                'recorded_by' => $e->recorder?->name,
                'decided_by' => $e->approver?->name,
                'rejection_reason' => $e->rejection_reason,
            ], fn ($v) => $v !== null && $v !== ''))->values()->toArray(),
        ];
    }

    private function pettyCashSummary(array $a): array
    {
        $q = $this->inSchools(PettyCash::query());
        if (! empty($a['from_date'])) {
            $q->where('entry_date', '>=', $a['from_date']);
        }
        if (! empty($a['to_date'])) {
            $q->where('entry_date', '<=', $a['to_date']);
        }

        $balance = $this->inSchools(PettyCash::query())->orderByDesc('entry_date')->orderByDesc('id')->first()?->balance_after_cents;

        return [
            'total_topups' => $this->money((clone $q)->where('type', 'top_up')->sum('amount_cents')),
            'total_spent' => $this->money((clone $q)->where('type', 'expense')->sum('amount_cents')),
            'current_balance' => $this->money($balance?->cents() ?? 0),
        ];
    }

    private function recentPayments(array $a): array
    {
        $q = $this->inSchools(Payment::query())->with('student')->latest('paid_at');

        if (! empty($a['date'])) {
            $q->whereDate('paid_at', $a['date'] === 'today' ? now()->toDateString() : $a['date']);
        }

        $rows = $q->limit(min(50, (int) ($a['limit'] ?? 10)))->get()->map(fn ($p) => [
            'student' => $this->fullName($p->student),
            'amount' => $this->money($p->amount_cents->cents()),
            'method' => $this->statusValue($p->method),
            'date' => optional($p->paid_at)->format('d M Y H:i'),
        ])->toArray();

        return ['payments' => $rows, 'count' => count($rows)];
    }

    private function feeStructures(array $a): array
    {
        $q = $this->inSchools(FeeStructure::query())->with('feeItems');
        if (! empty($a['term_id'])) {
            $q->where('term_id', $a['term_id']);
        }

        $structures = $q->get()->map(fn ($fs) => [
            'name' => $fs->name,
            'total' => $this->money($fs->feeItems->sum(fn ($i) => $i->amount_cents->cents())),
            'fee_items' => $fs->feeItems->map(fn ($i) => [
                'name' => $i->name,
                'amount' => $this->money($i->amount_cents->cents()),
            ])->toArray(),
        ])->toArray();

        return ['fee_structures' => $structures, 'count' => count($structures)];
    }

    // ─── Students ───────────────────────────────────────────────────────────────

    private function studentStats(array $a): array
    {
        $base = $this->inSchools(Enrollment::query());
        if (! empty($a['academic_year_id'])) {
            $base->where('academic_year_id', $a['academic_year_id']);
        }

        $gender = fn (string $g, bool $activeOnly) => (clone $base)
            ->when($activeOnly, fn ($q) => $q->where('status', 'active'))
            ->whereHas('student', fn ($s) => $s->where('gender', $g))->count();

        $total = (clone $base)->count();
        $active = (clone $base)->where('status', 'active')->count();

        return [
            'total_enrolled' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'boys_total' => $gender('male', false),
            'girls_total' => $gender('female', false),
            'active_boys' => $gender('male', true),
            'active_girls' => $gender('female', true),
        ];
    }

    private function classDistribution(array $a): array
    {
        $q = $this->inSchools(Enrollment::query())->select('school_class_id', DB::raw('COUNT(*) as count'))
            ->where('status', 'active')
            ->groupBy('school_class_id')
            ->with('schoolClass');

        if (! empty($a['academic_year_id'])) {
            $q->where('academic_year_id', $a['academic_year_id']);
        }

        $classes = $q->get()->map(fn ($r) => [
            'class' => $r->schoolClass?->name ?? 'Unknown',
            'count' => $r->count,
        ])->sortByDesc('count')->values()->toArray();

        return ['classes' => $classes, 'total_classes' => count($classes)];
    }

    private function studentList(array $a): array
    {
        $q = $this->students()->with(['currentEnrollment.schoolClass'])
            ->where('status', $a['status'] ?? 'active');

        if (! empty($a['search'])) {
            $needle = $this->like($a['search']);
            $q->where(fn ($s) => $s
                ->where('first_name', 'like', $needle)
                ->orWhere('last_name', 'like', $needle));
        }
        if (! empty($a['class_name'])) {
            $q->whereHas('currentEnrollment.schoolClass', fn ($c) => $c->where('name', 'like', $this->like($a['class_name'])));
        }

        $students = $q->limit(min(100, (int) ($a['limit'] ?? 15)))->get()->map(fn ($s) => [
            'name' => $this->fullName($s),
            'admission_number' => $s->currentEnrollment?->admission_number ?? 'N/A',
            'class' => $s->currentEnrollment?->schoolClass?->name ?? 'N/A',
            'gender' => $s->gender,
            'status' => $s->status,
            'date_of_birth' => $s->date_of_birth?->format('d M Y'),
        ])->toArray();

        return ['students' => $students, 'count' => count($students), 'note' => 'All fields included. Present every column the user asked for.'];
    }

    private function newEnrollments(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();

        $rows = $this->inSchools(Enrollment::query())->with(['student', 'schoolClass'])
            ->whereBetween('admitted_at', [$from, $to])
            ->latest('admitted_at')
            ->limit(min(100, (int) ($a['limit'] ?? 20)))
            ->get()
            ->map(fn ($e) => [
                'student' => $this->fullName($e->student),
                'admission_number' => $e->admission_number ?? 'N/A',
                'class' => $e->schoolClass?->name,
                'admitted_at' => optional($e->admitted_at)->format('d M Y'),
            ])->toArray();

        return ['period' => "{$from} to {$to}", 'count' => count($rows), 'enrollments' => $rows];
    }

    private function academicYears(): array
    {
        $years = $this->inSchools(AcademicYear::query())->with('terms')->orderByDesc('start_date')->get()->map(fn ($y) => [
            'name' => $y->name,
            'is_current' => (bool) $y->is_current,
            'terms' => $y->terms->map(fn ($t) => [
                'name' => $t->name,
                'is_current' => (bool) $t->is_current,
                'start' => optional($t->start_date)->format('d M Y'),
                'end' => optional($t->end_date)->format('d M Y'),
            ])->toArray(),
        ])->toArray();

        return ['academic_years' => $years, 'count' => count($years)];
    }

    // ─── Payroll ────────────────────────────────────────────────────────────────

    private function payrollSummary(array $a): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $a['month'] ?? '') ? $a['month'] : now()->format('Y-m');
        [$year, $mon] = array_map('intval', explode('-', $month));
        $label = Carbon::create($year, max(1, min(12, $mon)), 1)->format('F Y');

        $q = $this->inSchools(Payroll::query())->where('year', $year)->where('month', $mon);
        $count = (clone $q)->count();

        if ($count === 0) {
            return [
                'month' => $label,
                'employees_on_payroll' => 0,
                'message' => "No payroll entries found for {$label}. Payroll may not have been processed for this month yet.",
            ];
        }

        $basic = (clone $q)->sum('basic_salary_cents');
        $allowances = (clone $q)->sum('allowances_cents');

        return [
            'month' => $label,
            'employees_on_payroll' => $count,
            'paid' => (clone $q)->where('status', 'paid')->count(),
            'pending' => (clone $q)->where('status', 'pending')->count(),
            'total_basic_salary' => $this->money($basic),
            'total_allowances' => $this->money($allowances),
            'total_deductions' => $this->money((clone $q)->sum('deductions_cents')),
            'total_gross_salary' => $this->money($basic + $allowances),
            'total_net_salary' => $this->money((clone $q)->sum('net_salary_cents')),
        ];
    }

    private function employeeList(array $a): array
    {
        $q = $this->inSchools(Employee::query());
        if (! empty($a['department'])) {
            $q->where('department', 'like', $this->like($a['department']));
        }

        $employees = $q->limit(min(100, (int) ($a['limit'] ?? 20)))->get()->map(fn ($e) => [
            'name' => $e->full_name,
            'staff_number' => $e->staff_number,
            'role' => $e->role,
            'department' => $e->department,
            'status' => $e->status,
        ])->toArray();

        return ['employees' => $employees, 'count' => count($employees)];
    }

    // ─── Exams ──────────────────────────────────────────────────────────────────
    // Results come from the same services as the Results screen, so the assistant and the screens always agree:
    // marks are percentages of the exam's total, positions are per class, pass mark follows the grading scale.

    private function examById(array $a): ?Exam
    {
        return isset($a['exam_id']) ? $this->inSchools(Exam::query())->find($a['exam_id']) : null;
    }

    /** @return Collection<int, SchoolClass> the classes of an exam, optionally one by name */
    private function examClasses(Exam $exam, array $a)
    {
        $classes = ExamScope::classesFor($exam)->get();
        if (! empty($a['class_name'])) {
            $needle = mb_strtolower($a['class_name']);
            $classes = $classes->filter(fn ($c) => str_contains(mb_strtolower(ExamScope::classLabel($c)), $needle))->values();
        }

        return $classes;
    }

    private function examList(array $a): array
    {
        // Exam has no schoolClass BelongsTo; it has classes() BelongsToMany + all_classes bool
        // Date fields are start_date / end_date, not exam_date
        $q = $this->inSchools(Exam::query())->with('classes')->latest('start_date');
        if (! empty($a['status']) && $a['status'] !== 'all') {
            $q->where('status', $a['status']);
        }
        if (! empty($a['class_name'])) {
            $q->where(fn ($w) => $w->where('all_classes', true)
                ->orWhereHas('classes', fn ($c) => $c->where('name', 'like', '%'.$a['class_name'].'%')));
        }

        $exams = $q->limit($a['limit'] ?? 10)->get()->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'type' => $e->type,
            'classes' => $e->all_classes ? 'All Classes' : $e->classes->pluck('name')->join(', '),
            'status' => $e->status,
            'out_of' => $e->out_of,
            'marks_deadline' => optional($e->marks_deadline)->format('d M Y'),
            'comments_required_before_publishing' => (bool) $e->require_comments,
            'start_date' => optional($e->start_date)->format('d M Y'),
            'end_date' => optional($e->end_date)->format('d M Y'),
        ])->toArray();

        return ['exams' => $exams, 'count' => count($exams)];
    }

    private function examResultsSummary(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }

        $results = app(ExamResultsService::class);
        $analysis = app(ExamAnalysisService::class);
        $classes = [];
        foreach ($this->examClasses($exam, $a) as $class) {
            $r = $analysis->analyse($exam, $class);
            if ($r['overall']['ranked'] === 0) {
                continue;
            }
            $classes[] = [
                'class' => $r['class']['label'],
                'pupils_with_results' => $r['overall']['ranked'],
                'class_average_percent' => $r['overall']['class_average'],
                'pass_rate_percent' => $r['overall']['pass_rate'],
                'grade_distribution' => $r['overall']['grades'],
                'divisions' => $r['overall']['divisions'],
            ];
        }

        if (! $classes) {
            return ['exam' => $exam->name, 'status' => $exam->status, 'message' => 'No results recorded yet.'];
        }

        $completeness = $results->completeness($exam);

        return [
            'exam' => $exam->name,
            'status' => $exam->status,
            'note' => 'All marks are percentages of the exam total ('.$exam->out_of.'). Pass mark follows the school grading scale.',
            'marks_entered' => $completeness['entered'].' of '.$completeness['expected'],
            'total_students' => array_sum(array_column($classes, 'pupils_with_results')),
            'classes' => $classes,
        ];
    }

    private function topStudents(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }

        $results = app(ExamResultsService::class);
        $rows = collect();
        foreach ($this->examClasses($exam, $a) as $class) {
            $label = ExamScope::classLabel($class);
            foreach ($results->classResults($exam, $class)['rows'] as $row) {
                if ($row['average'] === null) {
                    continue;
                }
                $rows->push([
                    'student' => $row['full_name'],
                    'class' => $label,
                    'position_in_class' => $row['position'],
                    'average_percent' => $row['average'],
                    'grade' => $row['grade'],
                    'division' => $row['division']['label'] ?? null,
                    'subjects_missing' => $row['missing'],
                ]);
            }
        }

        $top = $rows->sortByDesc('average_percent')->take($a['limit'] ?? 10)->values()
            ->map(fn ($r, $i) => ['rank' => $i + 1] + $r)->toArray();

        return ['exam' => $exam->name, 'top_students' => $top, 'count' => count($top)];
    }

    private function subjectAverages(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }

        $results = app(ExamResultsService::class);
        $subjects = [];
        foreach ($this->examClasses($exam, $a) as $class) {
            foreach ($results->classResults($exam, $class)['subjects'] as $s) {
                if ($s['sat'] === 0) {
                    continue;
                }
                $k = $s['name'];
                $subjects[$k]['sat'] = ($subjects[$k]['sat'] ?? 0) + $s['sat'];
                $subjects[$k]['sum'] = ($subjects[$k]['sum'] ?? 0) + $s['average'] * $s['sat'];
                $subjects[$k]['highest'] = max($subjects[$k]['highest'] ?? 0, $s['highest']);
                $subjects[$k]['lowest'] = min($subjects[$k]['lowest'] ?? 100, $s['lowest']);
            }
        }

        $rows = collect($subjects)->map(fn ($v, $name) => [
            'subject' => $name,
            'average_percent' => round($v['sum'] / $v['sat'], 1),
            'highest' => (float) $v['highest'],
            'lowest' => (float) $v['lowest'],
            'students' => (int) $v['sat'],
        ])->sortByDesc('average_percent')->values()->toArray();

        return ['exam' => $exam->name, 'subject_averages' => $rows, 'count' => count($rows)];
    }

    /** Who still owes marks, class by class and subject by subject. */
    private function examProgress(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }

        $grid = app(ExamProgressService::class)->grid($exam);
        $waiting = [];
        foreach ($grid['classes'] as $c) {
            foreach ($c['subjects'] as $s) {
                if (! $s['complete'] && $s['total'] > 0) {
                    $waiting[] = [
                        'class' => $c['label'],
                        'subject' => $s['name'],
                        'entered' => $s['entered'].' of '.$s['total'],
                        'teachers' => collect($s['teachers'])->pluck('name')->join(', ') ?: 'nobody assigned',
                    ];
                }
            }
        }

        return [
            'exam' => $exam->name,
            'status' => $exam->status,
            'marks_entered' => $grid['entered'].' of '.$grid['total'],
            'marks_deadline' => $grid['deadline'],
            'deadline_passed' => $grid['deadline_passed'],
            'waiting_for_marks' => array_slice($waiting, 0, 40),
            'subjects_waiting' => count($waiting),
        ];
    }

    private function examAnalysis(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }
        $class = $this->examClasses($exam, $a)->first();
        if (! $class) {
            return ['message' => 'No class of this exam matches. Use get_exam_list to see which classes sit it.'];
        }

        $r = app(ExamAnalysisService::class)->analyse($exam, $class);

        return [
            'exam' => $exam->name,
            'class' => $r['class']['label'],
            'class_average_percent' => $r['overall']['class_average'],
            'pass_rate_percent' => $r['overall']['pass_rate'],
            'pass_mark_percent' => $r['pass_mark'],
            'grade_distribution' => $r['overall']['grades'],
            'weakest_subjects' => collect($r['subjects'])->filter(fn ($s) => $s['average'] !== null)->sortBy('average')->take(3)->map(fn ($s) => ['subject' => $s['name'], 'average_percent' => $s['average'], 'pass_rate_percent' => $s['pass_rate']])->values()->all(),
            'strongest_subjects' => collect($r['subjects'])->filter(fn ($s) => $s['average'] !== null)->sortByDesc('average')->take(3)->map(fn ($s) => ['subject' => $s['name'], 'average_percent' => $s['average']])->values()->all(),
            'top_pupils' => collect($r['top'])->map(fn ($p) => ['name' => $p['full_name'], 'average_percent' => $p['average'], 'grade' => $p['grade']])->all(),
            'pupils_needing_support' => collect($r['weakest'])->map(fn ($p) => ['name' => $p['full_name'], 'average_percent' => $p['average'], 'grade' => $p['grade']])->all(),
            'class_average_by_exam' => collect($r['trend'])->map(fn ($t) => ['exam' => $t['name'], 'average_percent' => $t['average']])->all(),
        ];
    }

    /** Where the report-card comments stand: class teacher submitted, head approved. */
    private function examCommentsStatus(array $a): array
    {
        $exam = $this->examById($a);
        if (! $exam) {
            return ['message' => 'No exam found with that id. Use get_exam_list to find the id.'];
        }

        $status = app(ExamCommentService::class)->status($exam);

        return [
            'exam' => $exam->name,
            'comments_required_before_publishing' => $status['required'],
            'all_classes_signed_off' => $status['complete'],
            'classes' => collect($status['classes'])->map(fn ($c) => [
                'class' => $c['label'],
                'pupils' => $c['pupils'],
                'class_teacher' => $c['teacher_signed'] ? 'submitted by '.$c['teacher_signed']['by'] : 'waiting ('.$c['teacher_missing'].' comments missing)',
                'head' => $c['head_signed'] ? 'approved by '.$c['head_signed']['by'] : 'waiting ('.$c['head_missing'].' comments missing)',
            ])->all(),
        ];
    }

    private function studentExamHistory(array $a): array
    {
        $student = $this->findStudents($a['student_name'] ?? '', 1)->first();
        if (! $student) {
            return ['message' => "No student found matching '".($a['student_name'] ?? '')."'."];
        }

        $h = app(ExamAnalysisService::class)->studentHistory($student);

        return [
            'student' => $h['student']['name'],
            'class' => $h['student']['class'],
            'exams' => collect($h['history'])->map(fn ($e) => [
                'exam' => $e['name'], 'term' => $e['term'], 'average_percent' => $e['average'], 'grade' => $e['grade'],
                'position' => $e['position'] ? $e['position'].' of '.$e['class_size'] : null, 'division' => $e['division']['label'] ?? null,
            ])->all(),
        ];
    }

    // ─── Suppliers ──────────────────────────────────────────────────────────────

    private function supplierOutstanding(array $a): array
    {
        // SupplierBill open statuses: 'approved' and 'part_paid' (not 'unpaid'/'partial')
        // Use balanceCents (total_cents - paid_cents) for actual amount owed
        $rows = $this->inSchools(SupplierBill::query())->with('supplier')
            ->whereIn('status', SupplierBill::OPEN)
            ->selectRaw('supplier_id, SUM(total_cents - paid_cents)/100 as balance_owed, SUM(total_cents)/100 as total_billed, COUNT(*) as bill_count')
            ->groupBy('supplier_id')
            ->orderByDesc('balance_owed')
            ->limit($a['limit'] ?? 10)
            ->get()
            ->map(fn ($r) => [
                'supplier' => $r->supplier?->name,
                'balance_owed' => 'TZS '.number_format($r->balance_owed),
                'total_billed' => 'TZS '.number_format($r->total_billed),
                'bill_count' => $r->bill_count,
            ])->toArray();

        return ['outstanding' => $rows, 'count' => count($rows)];
    }

    private function supplierSpend(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();

        // Date column is payment_date; supplier is directly on the payment
        $total = $this->inSchools(SupplierPayment::query())->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->where('status', 'approved')
            ->sum('amount_cents') / 100;

        $bySupplier = $this->inSchools(SupplierPayment::query())->with('supplier')
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->where('status', 'approved')
            ->selectRaw('supplier_id, SUM(amount_cents)/100 as total')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'supplier' => $r->supplier?->name ?? 'Unknown',
                'paid' => 'TZS '.number_format($r->total),
            ])->toArray();

        return [
            'period' => "{$from} to {$to}",
            'total_paid' => 'TZS '.number_format($total),
            'top_suppliers' => $bySupplier,
        ];
    }

    private function recentPurchases(array $a): array
    {
        // GoodsReceipt: date column is receipt_date; no total_cents (use bill total)
        $rows = $this->inSchools(GoodsReceipt::query())->with(['supplier', 'bill'])
            ->where('status', GoodsReceipt::RECEIVED)
            ->latest('receipt_date')
            ->limit($a['limit'] ?? 10)
            ->get()
            ->map(fn ($r) => [
                'grn_number' => $r->grn_number,
                'supplier' => $r->supplier?->name,
                'notes' => $r->notes,
                'bill_total' => $r->bill ? 'TZS '.number_format($r->bill->total_cents / 100) : null,
                'received_date' => optional($r->receipt_date)->format('d M Y'),
            ])->toArray();

        return ['purchases' => $rows, 'count' => count($rows)];
    }

    // ─── School ─────────────────────────────────────────────────────────────────

    private function schoolOverview(): array
    {
        $school = app()->bound('active_school') ? app('active_school') : null;
        $year = $this->inSchools(AcademicYear::query())->where('is_current', true)->first();
        $term = $this->terms()->where('is_current', true)->with('academicYear')->first();

        return [
            'school_name' => $school?->name ?? 'All schools',
            'active_students' => $this->inSchools(Enrollment::query())->where('status', 'active')->count(),
            'total_classes' => $this->inSchools(SchoolClass::query())->count(),
            'current_academic_year' => $year?->name ?? 'Not set',
            'current_term' => $term ? "{$term->name} ({$term->academicYear?->name})" : 'Not set',
            'term_dates' => $term
                ? optional($term->start_date)->format('d M Y').' – '.optional($term->end_date)->format('d M Y')
                : null,
        ];
    }

    private function classList(array $a): array
    {
        $classes = $this->inSchools(SchoolClass::query())->orderBy('sort_order')->get()->map(function ($c) use ($a) {
            $enrolled = $this->inSchools(Enrollment::query())->where('school_class_id', $c->id)->where('status', 'active')
                ->when(! empty($a['academic_year_id']), fn ($q) => $q->where('academic_year_id', $a['academic_year_id']))
                ->count();

            return [
                'name' => $c->name,
                'capacity' => $c->capacity,
                'enrolled' => $enrolled,
                'occupancy' => $c->capacity ? round(($enrolled / $c->capacity) * 100).'%' : 'N/A',
            ];
        })->toArray();

        return ['classes' => $classes, 'total' => count($classes)];
    }

    private function currentTerm(): array
    {
        $term = $this->terms()->where('is_current', true)->with('academicYear')->first()
            ?? $this->terms()->with('academicYear')->latest('start_date')->first();

        if (! $term) {
            return ['message' => 'No academic term configured yet.'];
        }

        return [
            'term_name' => $term->name,
            'term_number' => $term->number,
            'academic_year' => $term->academicYear?->name,
            'is_current' => (bool) $term->is_current,
            'start_date' => optional($term->start_date)->format('d M Y'),
            'end_date' => optional($term->end_date)->format('d M Y'),
            'days_remaining' => $term->end_date ? max(0, (int) now()->diffInDays($term->end_date, false)) : null,
        ];
    }

    private function subjectList(): array
    {
        $subjects = $this->inSchools(Subject::query())->where('is_active', true)->orderBy('name')->get()
            ->map(fn ($s) => ['name' => $s->name, 'code' => $s->code])->toArray();

        return ['subjects' => $subjects, 'count' => count($subjects)];
    }

    // ─── Attendance ─────────────────────────────────────────────────────────────
    // A late pupil counts as attending, so the rate is (present + late) / records.

    private function attendanceQuery(array $a, ?string $from = null, ?string $to = null)
    {
        $from ??= $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to ??= $a['to_date'] ?? now()->toDateString();

        return $this->inSchools(Attendance::query())->whereBetween('date', [$from, $to])
            ->when(! empty($a['class_name']), fn ($q) => $q->whereHas('schoolClass', fn ($c) => $c->where('name', 'like', $this->like($a['class_name']))));
    }

    private function rate(int $present, int $late, int $total): ?string
    {
        return $total > 0 ? round((($present + $late) / $total) * 100, 1).'%' : null;
    }

    private function attendanceSummary(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();

        $rows = $this->attendanceQuery($a, $from, $to)
            ->selectRaw('school_class_id, status, COUNT(*) as n')->groupBy('school_class_id', 'status')->get();
        $classes = $this->inSchools(SchoolClass::query())->whereIn('id', $rows->pluck('school_class_id'))->pluck('name', 'id');

        $tot = fn ($rs, $s) => (int) $rs->where('status', $s)->sum('n');
        $all = ['present' => $tot($rows, 'present'), 'late' => $tot($rows, 'late'), 'absent' => $tot($rows, 'absent')];
        $records = array_sum($all);

        return [
            'period' => "{$from} to {$to}",
            'total_records' => $records,
            'present' => $all['present'],
            'absent' => $all['absent'],
            'late' => $all['late'],
            'attendance_rate' => $this->rate($all['present'], $all['late'], $records) ?? 'N/A',
            'by_class' => $rows->groupBy('school_class_id')->map(function ($rs, $id) use ($classes, $tot) {
                $p = $tot($rs, 'present');
                $l = $tot($rs, 'late');
                $ab = $tot($rs, 'absent');

                return [
                    'class' => $classes[$id] ?? 'Unknown',
                    'total' => $p + $l + $ab, 'present' => $p, 'late' => $l, 'absent' => $ab,
                    'rate' => $this->rate($p, $l, $p + $l + $ab) ?? 'N/A',
                ];
            })->values()->all(),
        ];
    }

    private function studentAttendance(array $a): array
    {
        $student = $this->findStudents($a['student_name'] ?? '', 1)->first();
        if (! $student) {
            return ['message' => "No student found matching '{$a['student_name']}'."];
        }

        $q = $this->inSchools(Attendance::query())->where('student_id', $student->id)
            ->when(! empty($a['from_date']), fn ($q) => $q->where('date', '>=', $a['from_date']))
            ->when(! empty($a['to_date']), fn ($q) => $q->where('date', '<=', $a['to_date']));

        $count = fn (string $s) => (clone $q)->where('status', $s)->count();
        $present = $count('present');
        $absent = $count('absent');
        $late = $count('late');
        $total = $present + $absent + $late;

        return [
            'student' => $this->fullName($student),
            'total_days_recorded' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'attendance_rate' => $this->rate($present, $late, $total) ?? 'N/A',
            'recent_absences' => (clone $q)->where('status', 'absent')->latest('date')->limit(5)->get()
                ->map(fn ($r) => Carbon::parse($r->date)->format('d M Y'))->all(),
        ];
    }

    private function attendanceAbsentees(array $a): array
    {
        $date = $a['date'] ?? now()->toDateString();
        $rows = $this->attendanceQuery($a, $date, $date)->whereIn('status', ['absent', 'late'])
            ->with(['student', 'schoolClass'])->get();

        $map = fn ($r) => array_filter([
            'name' => $this->fullName($r->student),
            'class' => $r->schoolClass?->name,
            'remarks' => $r->remarks,
        ]);

        return [
            'date' => $date,
            'absent' => $rows->where('status', 'absent')->map($map)->values()->all(),
            'late' => $rows->where('status', 'late')->map($map)->values()->all(),
            'message' => $rows->isEmpty() ? 'Nobody was marked absent or late (or no register was taken that day).' : null,
        ];
    }

    private function lowAttendanceStudents(array $a): array
    {
        $from = $a['from_date'] ?? now()->startOfMonth()->toDateString();
        $to = $a['to_date'] ?? now()->toDateString();
        $threshold = (int) ($a['below_percent'] ?? 80);

        $rows = $this->attendanceQuery($a, $from, $to)
            ->selectRaw("student_id, SUM(status = 'present') p, SUM(status = 'late') l, SUM(status = 'absent') ab")
            ->groupBy('student_id')->get()
            ->map(function ($r) {
                $total = $r->p + $r->l + $r->ab;

                return ['id' => $r->student_id, 'rate' => $total ? (($r->p + $r->l) / $total) * 100 : null, 'days' => $total, 'absent' => (int) $r->ab, 'late' => (int) $r->l];
            })->filter(fn ($r) => $r['rate'] !== null && $r['rate'] < $threshold)
            ->sortBy('rate')->take(min(100, (int) ($a['limit'] ?? 15)))->values();

        $students = $this->students()->with('currentEnrollment.schoolClass')->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        return [
            'period' => "{$from} to {$to}",
            'below_percent' => $threshold,
            'students' => $rows->map(fn ($r) => [
                'name' => $this->fullName($students[$r['id']] ?? null),
                'class' => $students[$r['id']]?->currentEnrollment?->schoolClass?->name,
                'days_recorded' => $r['days'], 'absent' => $r['absent'], 'late' => $r['late'],
                'attendance_rate' => round($r['rate'], 1).'%',
            ])->all(),
            'count' => $rows->count(),
        ];
    }

    // ─── Reporting / deep finance ───────────────────────────────────────────────

    private function revenueTrend(array $a): array
    {
        $months = max(1, min(24, (int) ($a['months'] ?? 6)));
        $rows = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $rows[] = [
                'month' => $date->format('M Y'),
                'cents' => (int) $this->inSchools(Payment::query())->whereYear('paid_at', $date->year)->whereMonth('paid_at', $date->month)->sum('amount_cents'),
            ];
        }

        $total = array_sum(array_column($rows, 'cents'));

        return [
            'period_months' => $months,
            'total_collected' => $this->money($total),
            'monthly_average' => $this->money($total / $months),
            'trend' => array_map(fn ($r) => ['month' => $r['month'], 'collected' => $this->money($r['cents'])], $rows),
        ];
    }

    /** Planned vs actual per category, from the budget's own lines. */
    private function budgetSummary(array $a): array
    {
        $year = ! empty($a['academic_year_id'])
            ? $this->inSchools(AcademicYear::query())->find($a['academic_year_id'])
            : $this->inSchools(AcademicYear::query())->where('is_current', true)->first();

        if (! $year) {
            return ['message' => 'No active academic year found.'];
        }

        $budget = $this->inSchools(Budget::query())->where('academic_year_id', $year->id)->whereIn('status', ['active', 'closed'])
            ->with('items')->orderByRaw("status = 'active' desc")->latest()->first();

        if (! $budget) {
            return ['academic_year' => $year->name, 'message' => 'No active budget found for this year.'];
        }

        $sum = app(BudgetActuals::class)->summary($budget);
        $money = fn ($cents) => 'TZS '.number_format($cents / 100);

        // Burn-rate: days elapsed vs days total in the year → project full-year spend
        $yearStart = $year->start_date ? Carbon::parse($year->start_date) : null;
        $yearEnd = $year->end_date ? Carbon::parse($year->end_date) : null;
        $today = now();
        $daysTotal = ($yearStart && $yearEnd) ? max(1, $yearStart->diffInDays($yearEnd)) : null;
        $daysElapsed = ($yearStart && $today->gt($yearStart)) ? min($daysTotal ?? 999, $yearStart->diffInDays($today)) : null;
        $daysRemaining = ($yearEnd && $today->lt($yearEnd)) ? $today->diffInDays($yearEnd) : 0;
        $yearProgress = ($daysTotal && $daysElapsed !== null) ? round($daysElapsed / $daysTotal * 100, 1) : null;

        // Daily burn rate and projection
        $dailyBurn = ($daysElapsed && $daysElapsed > 0) ? $sum['actual_cents'] / $daysElapsed : null;
        $projectedSpend = ($dailyBurn && $daysTotal) ? (int) ($dailyBurn * $daysTotal) : null;
        $projectedVariance = $projectedSpend !== null ? $projectedSpend - $sum['planned_cents'] : null;

        $lines = array_map(fn ($l) => [
            'category' => $l['category'] ?: 'General',
            'planned' => $money($l['planned_cents']),
            'actual' => $money($l['actual_cents']),
            'remaining' => $money(max(0, $l['remaining_cents'])),
            'used' => $l['percent'].'%',
            'over_budget' => $l['over'] ? 'YES ⚠' : 'No',
            'overspend_amount' => $l['over'] ? $money($l['actual_cents'] - $l['planned_cents']) : null,
        ], $sum['lines']);

        $result = [
            'academic_year' => $year->name,
            'budget_name' => $budget->name,
            'status' => $budget->status,
            'year_progress' => $yearProgress !== null ? $yearProgress.'% of year elapsed' : 'N/A',
            'days_remaining_in_year' => $daysRemaining > 0 ? $daysRemaining.' days' : 'Year ended',
            'total_planned' => $money($sum['planned_cents']),
            'total_actual_spent' => $money($sum['actual_cents']),
            'total_remaining' => $money(max(0, $sum['planned_cents'] - $sum['actual_cents'])),
            'utilisation' => $sum['planned_cents'] > 0 ? round($sum['actual_cents'] / $sum['planned_cents'] * 100, 1).'%' : 'N/A',
            'variance' => $money(abs($sum['planned_cents'] - $sum['actual_cents'])).' '.($sum['actual_cents'] > $sum['planned_cents'] ? 'OVER budget' : 'under budget'),
            'lines_over_budget' => $sum['over_count'],
            'categories_over_budget' => array_values(array_map(
                fn ($l) => $l['category'] ?: 'General',
                array_filter($sum['lines'], fn ($l) => $l['over'])
            )),
        ];

        if ($projectedSpend !== null) {
            $result['projected_full_year_spend'] = $money($projectedSpend);
            $result['projected_variance'] = $money(abs($projectedVariance)).' '.($projectedVariance > 0 ? 'OVER budget (projected)' : 'under budget (projected)');
            $result['daily_burn_rate'] = $money((int) $dailyBurn).'/day';
        }

        $result['by_category'] = $lines;

        return $result;
    }

    private function budgetForecast(array $a): array
    {
        $actuals = app(BudgetActuals::class);
        $money = fn ($cents) => 'TZS '.number_format($cents / 100);

        // Load all historical closed/active budgets ordered oldest → newest
        $budgets = $this->inSchools(Budget::query())->with(['items', 'academicYear'])
            ->whereIn('status', ['active', 'closed'])
            ->whereHas('academicYear')
            ->get()
            ->sortBy(fn ($b) => $b->academicYear?->start_date)
            ->values();

        if ($budgets->count() < 1) {
            return ['message' => 'No budget history found. Create and close at least one budget to enable forecasting.'];
        }

        // Collect per-category actuals across all years
        $categoryHistory = [];
        $yearLabels = [];

        foreach ($budgets as $budget) {
            $label = $budget->academicYear->name;
            $yearLabels[] = $label;
            $sum = $actuals->summary($budget);

            foreach ($sum['lines'] as $line) {
                $cat = $line['category'] ?: 'General';
                if (! isset($categoryHistory[$cat])) {
                    $categoryHistory[$cat] = [];
                }
                $categoryHistory[$cat][$label] = [
                    'planned_cents' => $line['planned_cents'],
                    'actual_cents' => $line['actual_cents'],
                ];
            }
        }

        // For each category compute: average actual, growth rate, and recommended forecast
        $forecastLines = [];
        foreach ($categoryHistory as $cat => $yearData) {
            $actuals_list = array_values(array_column($yearData, 'actual_cents'));
            $planned_list = array_values(array_column($yearData, 'planned_cents'));
            $n = count($actuals_list);

            $avgActual = $n > 0 ? array_sum($actuals_list) / $n : 0;
            $avgPlanned = $n > 0 ? array_sum($planned_list) / $n : 0;

            // Year-over-year growth rate (if ≥2 years of data)
            $growthRate = null;
            if ($n >= 2) {
                $first = $actuals_list[0];
                $last = $actuals_list[$n - 1];
                $growthRate = $first > 0 ? round((($last - $first) / $first) * 100, 1) : null;
            }

            // Forecast = last actual × (1 + avg annual growth), floor at avg actual
            $lastActual = $actuals_list[$n - 1] ?? 0;
            $annualGrowth = ($n >= 2 && $growthRate !== null) ? $growthRate / max(1, $n - 1) / 100 : 0.05;
            $annualGrowth = max(-0.5, min(0.5, $annualGrowth)); // cap between -50% and +50%
            $forecast = (int) ($lastActual > 0 ? $lastActual * (1 + $annualGrowth) : $avgActual * 1.05);

            // Confidence: high if 3+ years, medium if 2, low if 1
            $confidence = $n >= 3 ? 'High' : ($n === 2 ? 'Medium' : 'Low — only 1 year of data');

            $forecastLines[] = [
                'category' => $cat,
                'years_of_data' => $n,
                'average_actual_spend' => $money((int) $avgActual),
                'average_planned_budget' => $money((int) $avgPlanned),
                'last_year_actual' => $money($lastActual),
                'yoy_growth_rate' => $growthRate !== null ? $growthRate.'%' : 'N/A',
                'recommended_budget' => $money($forecast),
                'confidence' => $confidence,
            ];
        }

        // Sort by recommended budget descending
        usort($forecastLines, fn ($a, $b) => $b['recommended_budget'] <=> $a['recommended_budget']);

        $totalForecast = array_sum(array_map(fn ($l) => (int) str_replace(['TZS ', ','], '', $l['recommended_budget']), $forecastLines));
        $lastBudget = $budgets->last();
        $lastSum = $actuals->summary($lastBudget);

        return [
            'based_on_years' => implode(', ', $yearLabels),
            'years_analysed' => $budgets->count(),
            'last_year_total_actual' => $money($lastSum['actual_cents']),
            'recommended_total_next_budget' => $money($totalForecast),
            'forecast_method' => 'Year-over-year growth trend per category, capped at ±50% per year',
            'note' => 'Categories with only 1 year of data use a conservative +5% growth assumption.',
            'forecast_by_category' => $forecastLines,
        ];
    }

    private function budgetHistory(array $a): array
    {
        $actuals = app(BudgetActuals::class);
        $money = fn ($cents) => 'TZS '.number_format($cents / 100);

        $budgets = $this->inSchools(Budget::query())->with(['items', 'academicYear'])
            ->whereIn('status', ['active', 'closed'])
            ->whereHas('academicYear')
            ->get()
            ->sortBy(fn ($b) => $b->academicYear?->start_date)
            ->values();

        if ($budgets->isEmpty()) {
            return ['message' => 'No budget history found.'];
        }

        $history = $budgets->map(function ($budget) use ($actuals, $money) {
            $sum = $actuals->summary($budget);
            $utilisation = $sum['planned_cents'] > 0
                ? round($sum['actual_cents'] / $sum['planned_cents'] * 100, 1)
                : 0;

            return [
                'academic_year' => $budget->academicYear->name,
                'budget_name' => $budget->name,
                'status' => $budget->status,
                'total_planned' => $money($sum['planned_cents']),
                'total_actual' => $money($sum['actual_cents']),
                'utilisation' => $utilisation.'%',
                'over_budget' => $sum['over_count'] > 0 ? 'Yes ('.$sum['over_count'].' categories)' : 'No',
                'variance' => $money(abs($sum['planned_cents'] - $sum['actual_cents']))
                    .' '.($sum['actual_cents'] > $sum['planned_cents'] ? 'over' : 'under'),
            ];
        })->toArray();

        // Trend insight: is spending growing or shrinking?
        $actuals_trend = array_column($history, 'total_actual');
        $trend = count($history) >= 2 ? (end($actuals_trend) > reset($actuals_trend) ? 'increasing' : 'decreasing') : 'insufficient data';

        return [
            'years_on_record' => $budgets->count(),
            'spending_trend' => $trend,
            'history' => $history,
        ];
    }

    private function discountSummary(array $a): array
    {
        $q = Discount::query()->whereHas('invoice', fn ($i) => $this->inSchools($i));
        if (! empty($a['academic_year_id'])) {
            $q->whereHas('invoice', fn ($i) => $i->where('academic_year_id', $a['academic_year_id']));
        }

        $all = $q->get();

        return [
            'total_discounts' => $all->count(),
            'total_amount_discounted' => $this->money($all->sum(fn ($d) => $d->amount_cents->cents())),
            'by_type' => $all->groupBy(fn ($d) => $this->statusValue($d->type) ?: 'general')->map(fn ($g, $type) => [
                'type' => $type,
                'count' => $g->count(),
                'total' => $this->money($g->sum(fn ($d) => $d->amount_cents->cents())),
            ])->values()->all(),
        ];
    }

    private function refundSummary(array $a): array
    {
        $q = Refund::query()->whereIn('payment_id', $this->inSchools(Payment::query())->select('id'))
            ->when(! empty($a['from_date']), fn ($q) => $q->where('refunded_at', '>=', $a['from_date']))
            ->when(! empty($a['to_date']), fn ($q) => $q->where('refunded_at', '<=', $a['to_date'].' 23:59:59'));

        return [
            'total_refunds' => (clone $q)->count(),
            'total_amount_refunded' => $this->money((clone $q)->sum('amount_cents')),
            'recent_refunds' => (clone $q)->with('student')->latest('refunded_at')->limit(10)->get()->map(fn ($r) => [
                'student' => $this->fullName($r->student),
                'amount' => $this->money($r->amount_cents->cents()),
                'reason' => $r->reason,
                'date' => optional($r->refunded_at)->format('d M Y'),
            ])->all(),
        ];
    }

    private function studentFinancialSummary(array $a): array
    {
        $students = $this->findStudents($a['student_name'] ?? '');
        if ($students->isEmpty()) {
            return ['message' => "No student found matching '{$a['student_name']}'."];
        }

        return ['students' => $students->map(function ($s) {
            $invoices = $this->inSchools(Invoice::query())->where('student_id', $s->id)->with('payments')->get();
            $invoiced = $invoices->sum(fn ($i) => $i->total_amount_cents->cents() + $i->arrears_cents->cents() - $i->discount_cents->cents());
            $paid = $invoices->sum(fn ($i) => $i->paidCents());
            $balance = max(0, $invoiced - $paid);

            return [
                'name' => $this->fullName($s),
                'total_invoiced' => $this->money($invoiced),
                'total_paid' => $this->money($paid),
                'outstanding_balance' => $this->money($balance),
                'payment_status' => $balance <= 0 ? 'Fully Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'),
                'invoices_count' => $invoices->count(),
            ];
        })->all(), 'count' => $students->count()];
    }

    private function guardianList(array $a): array
    {
        $q = $this->guardians()->with('students');
        if (! empty($a['student_name'])) {
            $needle = $this->like($a['student_name']);
            $q->whereHas('students', fn ($s) => $s->where('first_name', 'like', $needle)->orWhere('last_name', 'like', $needle));
        }

        $guardians = $q->limit(min(100, (int) ($a['limit'] ?? 15)))->get()->map(fn ($g) => [
            'name' => $this->fullName($g),
            'phone' => $g->phone,
            'email' => $g->email,
            'students' => $g->students->map(fn ($s) => $this->fullName($s))->join(', '),
            'relation' => $g->students->first()?->pivot?->relation,
        ])->all();

        return ['guardians' => $guardians, 'count' => count($guardians)];
    }
}
