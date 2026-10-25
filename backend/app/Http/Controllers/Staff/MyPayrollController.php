<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\Employee;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MyPayrollController extends Controller
{
    // ── Resolve the authenticated user's own employee record ──────────────────
    private function myEmployee(): Employee
    {
        $employee = Employee::where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        abort_unless($employee, 404, __('messages.no_active_employee_record_linked_to_your'));

        return $employee;
    }

    // ── List own payroll entries ───────────────────────────────────────────────
    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->myEmployee();

        $query = Payroll::with(['school', 'employee', 'recorder'])
            ->where('employee_id', $employee->id)
            ->latest('year')->orderByDesc('month');

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        return PayrollResource::collection($query->paginate(50));
    }

    // ── Show single entry (own only) ───────────────────────────────────────────
    public function show(Payroll $payroll): JsonResponse
    {
        $employee = $this->myEmployee();
        abort_unless($payroll->employee_id === $employee->id, 403);

        return response()->json(new PayrollResource($payroll->load(['school', 'employee', 'recorder'])));
    }

    // ── Download own payslip PDF ───────────────────────────────────────────────
    public function downloadSlip(Payroll $payroll)
    {
        $employee = $this->myEmployee();
        abort_unless($payroll->employee_id === $employee->id, 403);

        $payroll->load(['employee', 'recorder']);
        $school = app()->bound('active_school') ? app('active_school') : $payroll->school;
        $rates = $this->getRatesArray();

        $pdf = Pdf::loadView('pdf.payslip', [
            'payroll' => $payroll,
            'school' => $school,
            'rates' => $rates,
        ])->setPaper('a4');

        $filename = sprintf('payslip_%s_%02d_%04d.pdf',
            $payroll->employee->staff_number,
            $payroll->month,
            $payroll->year,
        );

        return $pdf->download($filename);
    }

    // ── Download own annual earnings statement PDF ─────────────────────────────
    public function annualStatement(Request $request)
    {
        $employee = $this->myEmployee();
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

    // ── Helper ─────────────────────────────────────────────────────────────────
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
}
