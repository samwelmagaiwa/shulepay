<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller {
    public function index(Request $request): AnonymousResourceCollection {
        $query = Employee::with('school')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        return EmployeeResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'user_id'            => 'nullable|exists:users,id',
            'staff_number'       => 'required|string|max:50|unique:employees,staff_number',
            'full_name'          => 'required|string|max:255',
            'role'               => 'required|string|max:100',
            'department'         => 'nullable|string|max:100',
            'basic_salary_cents' => 'required|integer|min:0',
            'hire_date'          => 'required|date',
            'status'             => 'sometimes|in:active,on_leave,terminated',
        ]);

        $employee = Employee::create($data);
        AuditLog::record('employee.created', $employee, [], $data);

        return response()->json(new EmployeeResource($employee->load('school')), 201);
    }

    public function show(Employee $employee): JsonResponse {
        return response()->json(new EmployeeResource($employee->load('school')));
    }

    public function update(Request $request, Employee $employee): JsonResponse {
        $data = $request->validate([
            'user_id'            => 'nullable|exists:users,id',
            'staff_number'       => 'sometimes|required|string|max:50|unique:employees,staff_number,' . $employee->id,
            'full_name'          => 'sometimes|required|string|max:255',
            'role'               => 'sometimes|required|string|max:100',
            'department'         => 'nullable|string|max:100',
            'basic_salary_cents' => 'sometimes|required|integer|min:0',
            'hire_date'          => 'sometimes|required|date',
            'status'             => 'sometimes|in:active,on_leave,terminated',
        ]);

        $before = $employee->toArray();
        $employee->update($data);
        AuditLog::record('employee.updated', $employee, $before, $data);

        return response()->json(new EmployeeResource($employee->load('school')));
    }

    public function destroy(Employee $employee): JsonResponse {
        AuditLog::record('employee.deleted', $employee, $employee->toArray(), []);
        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }
}
