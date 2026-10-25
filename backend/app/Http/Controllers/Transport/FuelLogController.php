<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\FuelLogRequest;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Services\Transport\FuelLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fuel logs. Vehicles are looked up here rather than by route-model binding:
 * binding runs before the active school is set, so it would resolve another
 * school's vehicle.
 */
class FuelLogController extends Controller
{
    public function __construct(private readonly FuelLogService $fuel) {}

    public function index(Request $request, string $vehicle): JsonResponse
    {
        $range = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $vehicle = Vehicle::findOrFail($vehicle);

        // The rows are cut to the period, the summary describes the same period.
        $rows = $this->fuel->history($vehicle, $range['from'] ?? null, $range['to'] ?? null);

        return response()->json([
            'data' => $rows->map(fn (array $row) => collect($row)->except('stretch'))->values(),
            'summary' => $this->fuel->summary($vehicle, $range['from'] ?? null, $range['to'] ?? null),
        ]);
    }

    /** Fuel totals for every vehicle over a period. */
    public function fleet(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $this->fuel->fleet($range['from'] ?? null, $range['to'] ?? null)]);
    }

    public function store(FuelLogRequest $request, string $vehicle): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicle);
        $data = $request->validated();

        $this->fuel->assertOdometerFits($vehicle, $data['fuelled_on'], $data['odometer_km']);

        $log = $vehicle->fuelLogs()->create($data + ['recorded_by' => $request->user()->id]);

        return response()->json([
            'message' => 'Fuel log saved for '.$vehicle->plate_number.'.',
            'data' => $this->fuel->row($log),
        ], 201);
    }

    public function update(FuelLogRequest $request, string $log): JsonResponse
    {
        $log = $this->find($log);
        $data = $request->validated();

        $this->fuel->assertOdometerFits($log->vehicle, $data['fuelled_on'], $data['odometer_km'], $log->id);
        $log->update($data);

        return response()->json(['message' => __('messages.fuel_log_updated'), 'data' => $this->fuel->row($log)]);
    }

    public function destroy(string $log): JsonResponse
    {
        $this->find($log)->delete();

        return response()->json(['message' => __('messages.fuel_log_deleted')]);
    }

    /** A fuel log, only if its vehicle belongs to the active school. */
    private function find(string $id): FuelLog
    {
        return FuelLog::query()->whereHas('vehicle')->with('vehicle')->findOrFail($id);
    }
}
