<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $columns = ['id', 'full_name', 'department', 'role', 'staff_number'];
        $query = Employee::select($columns)
            ->where('status', 'active')
            ->orderBy('full_name');

        if (! $this->isTenantMode()) {
            $schoolId = $this->schoolIdForQuery($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }
        }

        return response()->json($query->get());
    }

    /** The list filters, shared by the page and its totals. `inactive` means anyone not currently working. */
    private function filtered(Request $request, $query)
    {
        return $query
            ->when($request->status === 'inactive', fn ($q) => $q->where('status', '!=', 'active'))
            ->when($request->filled('status') && $request->status !== 'inactive', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->department))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
                $q->where(fn ($w) => $w->where('full_name', 'like', $term)->orWhere('staff_number', 'like', $term)
                    ->orWhere('role', 'like', $term)->orWhere('department', 'like', $term));
            });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->filtered($request, Employee::with('school')->latest());
        $by = $this->filtered($request, Employee::query())->selectRaw('status, COUNT(*) AS n, SUM(basic_salary_cents) AS cents')->groupBy('status')->get();
        $active = $by->firstWhere('status', 'active');

        return EmployeeResource::collection($query->paginate(min(max($request->integer('per_page', 20), 5), 200)))->additional(['summary' => [
            'total' => (int) $by->sum('n'),
            'active' => (int) ($active->n ?? 0),
            'not_working' => (int) $by->where('status', '!=', 'active')->sum('n'),
            'monthly_basic_cents' => (int) ($active->cents ?? 0),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'staff_number' => 'required|string|max:50|unique:employees,staff_number',
            'full_name' => 'required|string|max:255',
            'role' => 'required|string|max:100',
            'department' => 'nullable|string|max:100',
            'basic_salary_cents' => 'required|integer|min:0',
            'hire_date' => 'required|date',
            'status' => 'sometimes|in:active,on_leave,terminated',
        ]);

        $employee = Employee::create($data);
        AuditLog::record('employee.created', $employee, [], $data);

        return response()->json(new EmployeeResource($employee->load('school')), 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return response()->json(new EmployeeResource($employee->load('school')));
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'staff_number' => 'sometimes|required|string|max:50|unique:employees,staff_number,'.$employee->id,
            'full_name' => 'sometimes|required|string|max:255',
            'role' => 'sometimes|required|string|max:100',
            'department' => 'nullable|string|max:100',
            'basic_salary_cents' => 'sometimes|required|integer|min:0',
            'hire_date' => 'sometimes|required|date',
            'status' => 'sometimes|in:active,on_leave,terminated',
        ]);

        $before = $employee->toArray();
        $employee->update($data);
        AuditLog::record('employee.updated', $employee, $before, $data);

        return response()->json(new EmployeeResource($employee->load('school')));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        AuditLog::record('employee.deleted', $employee, $employee->toArray(), []);
        $employee->delete();

        return response()->json(['message' => __('messages.employee_deleted')]);
    }
}
