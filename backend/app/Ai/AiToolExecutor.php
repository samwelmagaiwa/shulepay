<?php

namespace App\Ai;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Budget;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PettyCash;
use App\Models\Refund;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
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
                'get_budget_forecast' => $this->budgetForecast(),
                'get_budget_history' => $this->budgetHistory(),
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

        $stats = $q->selectRaw(
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

        $scope = fn () => $this->inSchools(Expense::query())->whereBetween('expense_date', [$from, $to])
            ->when(! empty($a['vendor']), fn ($q) => $q->where('vendor', 'like', $this->like($a['vendor'])))
            ->when(! empty($a['category_name']), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', $this->like($a['category_name']))));

        $by = $scope()->selectRaw('status, COUNT(*) AS n, SUM(amount_cents) AS cents')->groupBy('status')->get()->keyBy('status');

        $group = in_array($a['group_by'] ?? 'category', ['category', 'vendor', 'status', 'month'], true) ? ($a['group_by'] ?? 'category') : 'category';
        $key = match ($group) {
            'vendor' => "COALESCE(NULLIF(vendor, ''), 'No vendor')",
            'status' => 'status',
            'month' => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', expense_date)" : "DATE_FORMAT(expense_date, '%Y-%m')",
            default => 'category_id',
        };
        $rows = $scope()->when($group !== 'status', fn ($q) => $q->where('status', 'approved'))
            ->selectRaw("$key AS k, SUM(amount_cents) AS cents, COUNT(*) AS n")->groupBy('k')->orderByDesc('cents')->limit(25)->get();
        $names = $group === 'category' ? ExpenseCategory::whereIn('id', $rows->pluck('k'))->pluck('name', 'id') : collect();
        $breakdown = $rows->map(fn ($r) => [
            $group => $group === 'category' ? ($names[$r->k] ?? 'Uncategorised') : $r->k,
            'total' => $this->money($r->cents),
            'count' => (int) $r->n,
        ])->values()->toArray();

        return [
            'period' => "{$from} to {$to}",
            'note' => 'Only approved expenses count as spent.',
            'grand_total' => $this->money($by['approved']->cents ?? 0),
            'approved_count' => (int) ($by['approved']->n ?? 0),
            'waiting_for_approval' => ['count' => (int) ($by['pending']->n ?? 0), 'total' => $this->money($by['pending']->cents ?? 0)],
            'rejected_count' => (int) ($by['rejected']->n ?? 0),
            'grouped_by' => $group,
            'breakdown' => $breakdown,
        ];
    }

    private function expensesList(array $a): array
    {
        $limit = max(1, min(50, (int) ($a['limit'] ?? 15)));
        $q = $this->inSchools(Expense::query())->with(['category:id,name', 'recorder:id,name', 'approver:id,name'])
            ->when(! empty($a['status']), fn ($q) => $q->where('status', $a['status']))
            ->when(! empty($a['from_date']), fn ($q) => $q->where('expense_date', '>=', $a['from_date']))
            ->when(! empty($a['to_date']), fn ($q) => $q->where('expense_date', '<=', $a['to_date']))
            ->when(! empty($a['search']), function ($q) use ($a) {
                $t = $this->like($a['search']);
                $q->where(fn ($w) => $w->where('description', 'like', $t)->orWhere('vendor', 'like', $t)->orWhere('receipt_reference', 'like', $t)->orWhere('notes', 'like', $t));
            });
        $total = (clone $q)->count();
        $sum = (clone $q)->sum('amount_cents');
        $rows = $q->latest('expense_date')->latest('id')->limit($limit)->get();

        return [
            'matching' => $total,
            'shown' => $rows->count(),
            'total_of_matching' => $this->money($sum),
            'expenses' => $rows->map(fn ($e) => array_filter([
                'date' => $e->expense_date?->toDateString(),
                'description' => $e->description,
                'amount' => 'TZS '.number_format($e->amount_cents->tzs()),
                'category' => $e->category?->name,
                'vendor' => $e->vendor,
                'receipt_reference' => $e->receipt_reference,
                'status' => $e->status,
                'recorded_by' => $e->recorder?->name,
                'decided_by' => $e->approver?->name,
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
    private function budgetLines(Budget $budget): array
    {
        $lines = $budget->items->map(fn ($i) => [
            'category' => $i->category ?: 'General',
            'planned_cents' => (int) $i->planned_cents,
            'actual_cents' => (int) $i->actual_cents,
        ])->values()->all();

        $planned = array_sum(array_column($lines, 'planned_cents'));
        $actual = array_sum(array_column($lines, 'actual_cents'));

        return [
            'lines' => $lines,
            'planned_cents' => $planned,
            'actual_cents' => $actual,
            'over_count' => count(array_filter($lines, fn ($l) => $l['actual_cents'] > $l['planned_cents'])),
        ];
    }

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

        $s = $this->budgetLines($budget);
        $start = $year->start_date ? Carbon::parse($year->start_date) : null;
        $end = $year->end_date ? Carbon::parse($year->end_date) : null;
        $daysTotal = ($start && $end) ? max(1, $start->diffInDays($end)) : null;
        $daysElapsed = ($start && now()->gt($start)) ? min($daysTotal ?? PHP_INT_MAX, (int) $start->diffInDays(now())) : null;
        $progress = ($daysTotal && $daysElapsed !== null) ? round($daysElapsed / $daysTotal * 100, 1) : null;

        $result = [
            'academic_year' => $year->name,
            'budget_name' => $budget->name,
            'status' => $budget->status,
            'year_progress' => $progress !== null ? $progress.'% of year elapsed' : 'N/A',
            'total_planned' => $this->money($s['planned_cents']),
            'total_actual_spent' => $this->money($s['actual_cents']),
            'total_remaining' => $this->money(max(0, $s['planned_cents'] - $s['actual_cents'])),
            'utilisation' => $s['planned_cents'] > 0 ? round($s['actual_cents'] / $s['planned_cents'] * 100, 1).'%' : 'N/A',
            'variance' => $this->money(abs($s['planned_cents'] - $s['actual_cents'])).' '.($s['actual_cents'] > $s['planned_cents'] ? 'OVER budget' : 'under budget'),
            'lines_over_budget' => $s['over_count'],
        ];

        if ($daysElapsed && $daysTotal) {
            $daily = $s['actual_cents'] / $daysElapsed;
            $projected = (int) ($daily * $daysTotal);
            $result['daily_burn_rate'] = $this->money($daily).'/day';
            $result['projected_full_year_spend'] = $this->money($projected);
            $result['projected_variance'] = $this->money(abs($projected - $s['planned_cents'])).' '.($projected > $s['planned_cents'] ? 'OVER budget (projected)' : 'under budget (projected)');
        }

        $result['by_category'] = array_map(fn ($l) => [
            'category' => $l['category'],
            'planned' => $this->money($l['planned_cents']),
            'actual' => $this->money($l['actual_cents']),
            'remaining' => $this->money(max(0, $l['planned_cents'] - $l['actual_cents'])),
            'used' => $l['planned_cents'] > 0 ? round($l['actual_cents'] / $l['planned_cents'] * 100, 1).'%' : 'N/A',
            'over_budget' => $l['actual_cents'] > $l['planned_cents'] ? 'YES' : 'No',
        ], $s['lines']);

        return $result;
    }

    private function closedBudgets()
    {
        return $this->inSchools(Budget::query())->with(['items', 'academicYear'])
            ->whereIn('status', ['active', 'closed'])
            ->whereHas('academicYear')
            ->get()
            ->sortBy(fn ($b) => $b->academicYear?->start_date)
            ->values();
    }

    private function budgetForecast(): array
    {
        $budgets = $this->closedBudgets();
        if ($budgets->isEmpty()) {
            return ['message' => 'No budget history found. Create and close at least one budget to enable forecasting.'];
        }

        $history = [];
        foreach ($budgets as $budget) {
            foreach ($this->budgetLines($budget)['lines'] as $l) {
                $history[$l['category']][] = $l['actual_cents'];
            }
        }

        $lines = [];
        foreach ($history as $cat => $actuals) {
            $n = count($actuals);
            $avg = array_sum($actuals) / $n;
            $last = $actuals[$n - 1];
            $growth = ($n >= 2 && $actuals[0] > 0) ? (($last - $actuals[0]) / $actuals[0]) : null;
            $annual = $growth !== null ? max(-0.5, min(0.5, $growth / ($n - 1))) : 0.05;
            $forecast = (int) ($last > 0 ? $last * (1 + $annual) : $avg * 1.05);

            $lines[] = [
                'category' => $cat,
                'years_of_data' => $n,
                'average_actual_spend' => $this->money($avg),
                'last_year_actual' => $this->money($last),
                'yoy_growth_rate' => $growth !== null ? round($growth * 100, 1).'%' : 'N/A',
                'recommended_budget' => $this->money($forecast),
                'confidence' => $n >= 3 ? 'High' : ($n === 2 ? 'Medium' : 'Low — only 1 year of data'),
                '_cents' => $forecast,
            ];
        }
        usort($lines, fn ($x, $y) => $y['_cents'] <=> $x['_cents']);
        $total = array_sum(array_column($lines, '_cents'));

        return [
            'based_on_years' => $budgets->map(fn ($b) => $b->academicYear->name)->implode(', '),
            'years_analysed' => $budgets->count(),
            'recommended_total_next_budget' => $this->money($total),
            'forecast_method' => 'Year-over-year growth trend per category, capped at ±50% per year',
            'note' => 'Categories with only 1 year of data use a conservative +5% growth assumption.',
            'forecast_by_category' => array_map(fn ($l) => array_diff_key($l, ['_cents' => 1]), $lines),
        ];
    }

    private function budgetHistory(): array
    {
        $budgets = $this->closedBudgets();
        if ($budgets->isEmpty()) {
            return ['message' => 'No budget history found.'];
        }

        $rows = $budgets->map(function ($b) {
            $s = $this->budgetLines($b);

            return [
                'academic_year' => $b->academicYear->name,
                'budget_name' => $b->name,
                'status' => $b->status,
                'total_planned' => $this->money($s['planned_cents']),
                'total_actual' => $this->money($s['actual_cents']),
                'utilisation' => ($s['planned_cents'] > 0 ? round($s['actual_cents'] / $s['planned_cents'] * 100, 1) : 0).'%',
                'over_budget' => $s['over_count'] > 0 ? 'Yes ('.$s['over_count'].' categories)' : 'No',
                '_actual' => $s['actual_cents'],
            ];
        });

        return [
            'years_on_record' => $rows->count(),
            'spending_trend' => $rows->count() >= 2 ? ($rows->last()['_actual'] > $rows->first()['_actual'] ? 'increasing' : 'decreasing') : 'insufficient data',
            'history' => $rows->map(fn ($r) => array_diff_key($r, ['_actual' => 1]))->all(),
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
