<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\TanzaniaTaxService;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    // ── List ──────────────────────────────────────────────────────────────────
    private function filtered(Request $request, $query)
    {
        return $query
            ->when($request->filled('month'), fn ($q) => $q->where('month', $request->month))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->year))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
                $q->whereHas('employee', fn ($e) => $e->where('full_name', 'like', $term)->orWhere('staff_number', 'like', $term));
            });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->filtered($request, Payroll::with(['school', 'employee', 'recorder'])->latest());

        // Totals of the whole month (every page), for the cards
        $by = $this->filtered($request, Payroll::query())
            ->selectRaw('status, COUNT(*) AS n, SUM(basic_salary_cents + allowances_cents) AS gross, SUM(net_salary_cents) AS net, SUM(deductions_cents) AS ded')
            ->groupBy('status')->get();
        $paid = $by->firstWhere('status', 'paid');
        $pending = $by->where('status', '!=', 'paid');

        return PayrollResource::collection($query->paginate(min(max($request->integer('per_page', 50), 5), 200)))->additional(['summary' => [
            'count' => (int) $by->sum('n'),
            'gross_cents' => (int) $by->sum('gross'),
            'net_cents' => (int) $by->sum('net'),
            'deductions_cents' => (int) $by->sum('ded'),
            'paid_count' => (int) ($paid->n ?? 0),
            'paid_cents' => (int) ($paid->net ?? 0),
            'pending_count' => (int) $pending->sum('n'),
            'pending_cents' => (int) $pending->sum('net'),
        ]]);
    }

    // ── Create single entry ────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'allowances_cents' => 'sometimes|integer|min:0',
            'payment_method' => 'required|in:cash,bank,mpesa',
            'notes' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        if (Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])->where('year', $data['year'])->exists()) {
            return response()->json(['message' => __('messages.payroll_entry_already_exists_for_this_employee')], 422);
        }

        $basic = (int) $employee->getRawOriginal('basic_salary_cents');
        $allowances = (int) ($data['allowances_cents'] ?? 0);
        $gross = $basic + $allowances;

        $tax = $this->taxService()->compute($gross);

        $entry = Payroll::create([
            'employee_id' => $data['employee_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'basic_salary_cents' => $basic,
            'allowances_cents' => $allowances,
            'paye_cents' => $tax['paye_cents'],
            'nssf_employee_cents' => $tax['nssf_employee_cents'],
            'nssf_employer_cents' => $tax['nssf_employer_cents'],
            'wcf_cents' => $tax['wcf_cents'],
            'sdl_cents' => $tax['sdl_cents'],
            'deductions_cents' => $tax['total_employee_deductions_cents'],
            'net_salary_cents' => $gross - $tax['total_employee_deductions_cents'],
            'notes' => $data['notes'] ?? null,
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            'recorded_by' => Tenant::actorId(),
        ]);

        AuditLog::record('payroll.created', $entry, [], $entry->toArray());

        return response()->json(new PayrollResource($entry->load(['school', 'employee', 'recorder'])), 201);
    }

    // ── Show single ────────────────────────────────────────────────────────────
    public function show(Payroll $payroll): JsonResponse
    {
        return response()->json(new PayrollResource($payroll->load(['school', 'employee', 'recorder'])));
    }

    // ── Mark paid ──────────────────────────────────────────────────────────────
    public function markPaid(Payroll $payroll): JsonResponse
    {
        if ($payroll->status === 'paid') {
            return response()->json(['message' => __('messages.already_marked_as_paid')], 422);
        }

        $before = $payroll->toArray();
        $payroll->update(['status' => 'paid', 'payment_date' => now()->toDateString()]);
        AuditLog::record('payroll.marked_paid', $payroll, $before, ['status' => 'paid']);

        return response()->json(new PayrollResource($payroll->load(['school', 'employee', 'recorder'])));
    }

    // ── Bulk generate ──────────────────────────────────────────────────────────
    public function bulkGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'payment_method' => 'sometimes|in:cash,bank,mpesa',
        ]);

        $paymentMethod = $data['payment_method'] ?? 'bank';
        $tax = $this->taxService();

        $employees = Employee::where('status', 'active');
        if (! $this->isTenantMode()) {
            $schoolId = $this->schoolIdForQuery($request) ?? ($data['school_id'] ?? null);
            if (! $schoolId) {
                return response()->json(['message' => __('messages.no_active_school_set')], 422);
            }
            $employees->where('school_id', $schoolId);
        }
        $employees = $employees->get();

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($employees, $data, $paymentMethod, $tax, &$created, &$skipped) {
            foreach ($employees as $employee) {
                if (Payroll::where('employee_id', $employee->id)
                    ->where('month', $data['month'])->where('year', $data['year'])->exists()) {
                    $skipped[] = $employee->staff_number;

                    continue;
                }

                $basic = (int) $employee->getRawOriginal('basic_salary_cents');
                $deductions = $tax->compute($basic);

                $entry = Payroll::create([
                    'employee_id' => $employee->id,
                    'month' => $data['month'],
                    'year' => $data['year'],
                    'basic_salary_cents' => $basic,
                    'allowances_cents' => 0,
                    'paye_cents' => $deductions['paye_cents'],
                    'nssf_employee_cents' => $deductions['nssf_employee_cents'],
                    'nssf_employer_cents' => $deductions['nssf_employer_cents'],
                    'wcf_cents' => $deductions['wcf_cents'],
                    'sdl_cents' => $deductions['sdl_cents'],
                    'deductions_cents' => $deductions['total_employee_deductions_cents'],
                    'net_salary_cents' => $basic - $deductions['total_employee_deductions_cents'],
                    'payment_method' => $paymentMethod,
                    'status' => 'pending',
                    'recorded_by' => Tenant::actorId(),
                ]);

                AuditLog::record('payroll.bulk_generated', $entry, [], $entry->toArray());
                $created[] = $employee->staff_number;
            }
        });

        return response()->json([
            'message' => __('messages.bulk_payroll_generation_completed'),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // ── Payslip PDF ────────────────────────────────────────────────────────────
    public function downloadSlip(Payroll $payroll)
    {
        $payroll->load(['employee', 'recorder']);
        $school = app()->bound('active_school') ? app('active_school') : $payroll->school;

        $pdf = Pdf::loadView('pdf.payslip', [
            'payroll' => $payroll,
            'school' => $school,
            'rates' => $this->getRatesArray(),
        ])->setPaper('a4');

        $filename = sprintf('payslip_%s_%s_%02d_%04d.pdf',
            $payroll->employee->staff_number,
            str($payroll->employee->full_name)->slug('_'),
            $payroll->month,
            $payroll->year,
        );

        return $pdf->download($filename);
    }

    // ── Monthly summary PDF ────────────────────────────────────────────────────
    public function monthlySummary(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $entries = Payroll::with('employee')
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->orderBy('id')
            ->get();

        $school = app()->bound('active_school') ? app('active_school') : null;

        $totals = [
            'basic' => $entries->sum(fn ($e) => $e->getRawOriginal('basic_salary_cents')),
            'allowances' => $entries->sum(fn ($e) => $e->getRawOriginal('allowances_cents')),
            'gross' => $entries->sum(fn ($e) => $e->getRawOriginal('basic_salary_cents') + $e->getRawOriginal('allowances_cents')),
            'paye' => $entries->sum(fn ($e) => $e->getRawOriginal('paye_cents') ?? 0),
            'nssf_employee' => $entries->sum(fn ($e) => $e->getRawOriginal('nssf_employee_cents') ?? 0),
            'nssf_employer' => $entries->sum(fn ($e) => $e->getRawOriginal('nssf_employer_cents') ?? 0),
            'wcf' => $entries->sum(fn ($e) => $e->getRawOriginal('wcf_cents') ?? 0),
            'sdl' => $entries->sum(fn ($e) => $e->getRawOriginal('sdl_cents') ?? 0),
            'deductions' => $entries->sum(fn ($e) => $e->getRawOriginal('deductions_cents')),
            'net' => $entries->sum(fn ($e) => $e->getRawOriginal('net_salary_cents')),
        ];

        $pdf = Pdf::loadView('pdf.payroll_summary', [
            'entries' => $entries,
            'school' => $school,
            'month' => $request->month,
            'year' => $request->year,
            'totals' => $totals,
            'rates' => $this->getRatesArray(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("payroll_summary_{$request->month}_{$request->year}.pdf");
    }

    // ── Annual earnings statement PDF ──────────────────────────────────────────
    public function annualStatement(Request $request, Employee $employee)
    {
        $year = $request->integer('year', now()->year);

        $entries = Payroll::with('recorder')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $school = app()->bound('active_school') ? app('active_school') : null;

        $totals = [
            'basic' => $entries->sum(fn ($e) => $e->getRawOriginal('basic_salary_cents')),
            'allowances' => $entries->sum(fn ($e) => $e->getRawOriginal('allowances_cents')),
            'paye' => $entries->sum(fn ($e) => $e->getRawOriginal('paye_cents') ?? 0),
            'nssf_employee' => $entries->sum(fn ($e) => $e->getRawOriginal('nssf_employee_cents') ?? 0),
            'nssf_employer' => $entries->sum(fn ($e) => $e->getRawOriginal('nssf_employer_cents') ?? 0),
            'wcf' => $entries->sum(fn ($e) => $e->getRawOriginal('wcf_cents') ?? 0),
            'sdl' => $entries->sum(fn ($e) => $e->getRawOriginal('sdl_cents') ?? 0),
            'net' => $entries->sum(fn ($e) => $e->getRawOriginal('net_salary_cents')),
        ];

        $pdf = Pdf::loadView('pdf.payroll_annual', [
            'employee' => $employee,
            'school' => $school,
            'year' => $year,
            'entries' => $entries,
            'totals' => $totals,
        ])->setPaper('a4');

        return $pdf->download("annual_statement_{$employee->staff_number}_{$year}.pdf");
    }

    // ── Statutory rates ────────────────────────────────────────────────────────
    public function getRates(): JsonResponse
    {
        return response()->json($this->getRatesArray());
    }

    public function updateRates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nssf_employee_pct' => 'required|numeric|min:0|max:100',
            'nssf_employer_pct' => 'required|numeric|min:0|max:100',
            'wcf_pct' => 'required|numeric|min:0|max:100',
            'sdl_pct' => 'required|numeric|min:0|max:100',
        ]);

        DB::table('payroll_rates')->updateOrInsert(['id' => 1], array_merge($data, [
            'updated_at' => now(),
            'created_at' => now(),
        ]));

        return response()->json($this->getRatesArray());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function getRatesArray(): array
    {
        $row = DB::table('payroll_rates')->first();

        return $row ? (array) $row : [
            'nssf_employee_pct' => 10.0,
            'nssf_employer_pct' => 10.0,
            'wcf_pct' => 0.50,
            'sdl_pct' => 4.50,
        ];
    }

    private function taxService(): TanzaniaTaxService
    {
        return TanzaniaTaxService::fromRates($this->getRatesArray());
    }
}
