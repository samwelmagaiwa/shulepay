<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payroll::with(['school', 'employee', 'recorder'])->latest();

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return PayrollResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'allowances_cents' => 'sometimes|integer|min:0',
            'deductions_cents' => 'sometimes|integer|min:0',
            'payment_method' => 'required|in:cash,bank,mpesa',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $exists = Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Payroll entry already exists for this employee and period.'], 422);
        }

        $basic = (int) $employee->getRawOriginal('basic_salary_cents');
        $allowances = (int) ($data['allowances_cents'] ?? 0);
        $deductions = (int) ($data['deductions_cents'] ?? 0);
        $net = $basic + $allowances - $deductions;

        $entry = Payroll::create([
            'employee_id' => $data['employee_id'],
            'month' => $data['month'],
            'year' => $data['year'],
            'basic_salary_cents' => $basic,
            'allowances_cents' => $allowances,
            'deductions_cents' => $deductions,
            'net_salary_cents' => $net,
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            'recorded_by' => auth()->id(),
        ]);

        AuditLog::record('payroll.created', $entry, [], $entry->toArray());

        return response()->json(new PayrollResource($entry->load(['school', 'employee', 'recorder'])), 201);
    }

    public function show(Payroll $payroll): JsonResponse
    {
        return response()->json(new PayrollResource($payroll->load(['school', 'employee', 'recorder'])));
    }

    public function markPaid(Payroll $payroll): JsonResponse
    {
        if ($payroll->status === 'paid') {
            return response()->json(['message' => 'Payroll entry already marked as paid.'], 422);
        }

        $before = $payroll->toArray();
        $payroll->update([
            'status' => 'paid',
            'payment_date' => now()->toDateString(),
        ]);
        AuditLog::record('payroll.marked_paid', $payroll, $before, ['status' => 'paid']);

        return response()->json(new PayrollResource($payroll->load(['school', 'employee', 'recorder'])));
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => 'nullable|integer|exists:schools,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'payment_method' => 'sometimes|in:cash,bank,mpesa',
        ]);

        $paymentMethod = $data['payment_method'] ?? 'bank';

        // Prefer middleware-bound school, fall back to explicit school_id param, then user's school.
        $school = app()->bound('active_school') ? app('active_school') : null;
        $schoolId = $school?->id
            ?? ($data['school_id'] ?? null)
            ?? auth()->user()->school_id;

        if (!$schoolId) {
            return response()->json(['message' => 'No active school set.'], 422);
        }

        $employees = Employee::where('school_id', $schoolId)
            ->where('status', 'active')
            ->get();

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($employees, $data, $paymentMethod, &$created, &$skipped) {
            foreach ($employees as $employee) {
                $exists = Payroll::where('employee_id', $employee->id)
                    ->where('month', $data['month'])
                    ->where('year', $data['year'])
                    ->exists();

                if ($exists) {
                    $skipped[] = $employee->staff_number;
                    continue;
                }

                $basic = (int) $employee->getRawOriginal('basic_salary_cents');
                $net = $basic;

                $entry = Payroll::create([
                    'employee_id' => $employee->id,
                    'month' => $data['month'],
                    'year' => $data['year'],
                    'basic_salary_cents' => $basic,
                    'allowances_cents' => 0,
                    'deductions_cents' => 0,
                    'net_salary_cents' => $net,
                    'payment_method' => $paymentMethod,
                    'status' => 'pending',
                    'recorded_by' => auth()->id(),
                ]);

                AuditLog::record('payroll.bulk_generated', $entry, [], $entry->toArray());
                $created[] = $employee->staff_number;
            }
        });

        return response()->json([
            'message' => 'Bulk payroll generation completed.',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
