<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentTransportSubscription;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use App\Models\VehicleRoute;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    private function resolveSchoolId(Request $request): ?int
    {
        if ($request->filled('school_id')) {
            return (int) $request->school_id;
        }
        if (app()->bound('active_school')) {
            return app('active_school')->id;
        }

        return auth()->user()->school_id ?: null;
    }

    public function vehicles(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $query = Vehicle::query();
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $vehicles = $query
            ->with(['maintenanceRecords' => fn ($q) => $q->orderByDesc('service_date')->limit(1)])
            ->withCount(['subscriptions as subscribed_students' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->map(function ($v) {
                $last = $v->maintenanceRecords->first();
                $v->last_service_date = $last?->service_date;
                $v->total_maintenance_cost_cents = VehicleMaintenance::where('vehicle_id', $v->id)->sum('cost_cents');
                unset($v->maintenanceRecords);

                return $v;
            });

        return response()->json($vehicles);
    }

    public function storeVehicle(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:vehicles,plate_number',
            'school_id' => 'nullable|integer|exists:schools,id',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer|min:1990|max:2100',
            'capacity' => 'required|integer|min:1',
            'type' => 'required|in:bus,minibus,van,car',
            'color' => 'nullable|string',
            'status' => 'in:active,maintenance,retired',
            'driver_name' => 'nullable|string',
            'driver_phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['school_id'] = $validated['school_id']
            ?? (app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id);

        if (! $validated['school_id']) {
            return response()->json(['message' => 'Tafadhali chagua shule.'], 422);
        }

        $vehicle = Vehicle::create($validated);

        return response()->json($vehicle, 201);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $this->authorizeSchool($vehicle->school_id);

        $validated = $request->validate([
            'plate_number' => 'sometimes|string|unique:vehicles,plate_number,'.$vehicle->id,
            'make' => 'sometimes|string',
            'model' => 'sometimes|string',
            'year' => 'sometimes|integer|min:1990|max:2100',
            'capacity' => 'sometimes|integer|min:1',
            'type' => 'sometimes|in:bus,minibus,van,car',
            'color' => 'nullable|string',
            'status' => 'in:active,maintenance,retired',
            'driver_name' => 'nullable|string',
            'driver_phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $vehicle->update($validated);

        return response()->json($vehicle);
    }

    public function deleteVehicle(Vehicle $vehicle)
    {
        $this->authorizeSchool($vehicle->school_id);
        $vehicle->delete();

        return response()->noContent();
    }

    public function routes(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);
        $query = VehicleRoute::query();
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        return response()->json($query->get());
    }

    public function storeRoute(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'school_id' => 'nullable|integer|exists:schools,id',
            'description' => 'nullable|string',
            'start_point' => 'required|string',
            'end_point' => 'required|string',
            'distance_km' => 'nullable|numeric',
            'estimated_minutes' => 'nullable|integer',
            'monthly_fare_cents' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['school_id'] = $validated['school_id']
            ?? (app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id);

        if (! $validated['school_id']) {
            return response()->json(['message' => 'Tafadhali chagua shule.'], 422);
        }

        $route = VehicleRoute::create($validated);

        return response()->json($route, 201);
    }

    public function updateRoute(Request $request, VehicleRoute $route)
    {
        $this->authorizeSchool($route->school_id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'start_point' => 'sometimes|string',
            'end_point' => 'sometimes|string',
            'distance_km' => 'nullable|numeric',
            'estimated_minutes' => 'nullable|integer',
            'monthly_fare_cents' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $route->update($validated);

        return response()->json($route);
    }

    public function subscriptions(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $subs = StudentTransportSubscription::when($schoolId, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('school_id', $schoolId)))
            ->where('is_active', true)
            ->with(['student', 'vehicle', 'route'])
            ->get();

        return response()->json($subs);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'route_id' => 'nullable|exists:vehicle_routes,id',
            'direction' => 'in:both,morning,afternoon',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
        ]);

        // Deactivate any existing active subscription for this student
        StudentTransportSubscription::where('student_id', $validated['student_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $validated['is_active'] = true;
        $sub = StudentTransportSubscription::create($validated);
        $sub->load(['student', 'vehicle', 'route']);

        return response()->json($sub, 201);
    }

    public function unsubscribe(StudentTransportSubscription $subscription)
    {
        $subscription->update(['is_active' => false, 'end_date' => now()->toDateString()]);

        return response()->noContent();
    }

    public function maintenance(Request $request, Vehicle $vehicle)
    {
        $this->authorizeSchool($vehicle->school_id);

        $records = VehicleMaintenance::where('vehicle_id', $vehicle->id)
            ->with('recorder:id,name')
            ->orderByDesc('service_date')
            ->get();

        return response()->json($records);
    }

    public function storeMaintenance(Request $request, Vehicle $vehicle)
    {
        $this->authorizeSchool($vehicle->school_id);

        $validated = $request->validate([
            'type' => 'required|in:service,repair,inspection,fuel',
            'description' => 'required|string',
            'cost_cents' => 'nullable|integer|min:0',
            'service_date' => 'required|date',
            'next_service_date' => 'nullable|date|after:service_date',
            'odometer_km' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['vehicle_id'] = $vehicle->id;
        $validated['recorded_by'] = auth()->id();
        $validated['cost_cents'] = $validated['cost_cents'] ?? 0;

        // If type is 'maintenance', update vehicle status
        if ($validated['type'] === 'repair') {
            $vehicle->update(['status' => 'maintenance']);
        }

        $record = VehicleMaintenance::create($validated);

        return response()->json($record, 201);
    }

    public function vehicleSummary(Request $request)
    {
        $schoolId = $this->resolveSchoolId($request);

        $query = Vehicle::query();
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        $vehicles = $query->get();

        return response()->json([
            'total_vehicles' => $vehicles->count(),
            'active_vehicles' => $vehicles->where('status', 'active')->count(),
            'maintenance_vehicles' => $vehicles->where('status', 'maintenance')->count(),
            'retired_vehicles' => $vehicles->where('status', 'retired')->count(),
            'students_on_transport' => StudentTransportSubscription::when($schoolId, fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('school_id', $schoolId))
            )->where('is_active', true)->count(),
        ]);
    }

    private function authorizeSchool(int $schoolId): void
    {
        $user = auth()->user();
        // Superadmin can access any school's resources
        if ($user->hasRole('superadmin')) {
            return;
        }

        $userSchoolId = app()->bound('active_school')
            ? app('active_school')->id
            : $user->school_id;

        if ($userSchoolId !== $schoolId) {
            abort(403, 'Huna ruhusa ya kufikia rasilimali hii.');
        }
    }
}
