<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transport\VehicleDocumentRequest;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Transport\VehicleDocumentService;
use Illuminate\Http\JsonResponse;

/**
 * Vehicle compliance documents and their expiry alerts.
 *
 * Vehicles are looked up here rather than by route-model binding: binding runs
 * before the active school is set, so it would resolve another school's vehicle.
 */
class VehicleDocumentController extends Controller
{
    public function __construct(private readonly VehicleDocumentService $documents) {}

    /** Expired, about-to-expire and missing documents across the fleet. */
    public function alerts(): JsonResponse
    {
        return response()->json(['data' => $this->documents->alerts()]);
    }

    public function index(string $vehicle): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        $rows = $vehicle->documents()
            ->orderBy('expiry_date')
            ->get()
            ->each->setRelation('vehicle', $vehicle)
            ->map(fn (VehicleDocument $d) => $this->documents->row($d));

        return response()->json(['data' => $rows->values()]);
    }

    public function store(VehicleDocumentRequest $request, string $vehicle): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicle);

        $document = $vehicle->documents()->create($request->validated() + ['recorded_by' => $request->user()->id]);

        return response()->json([
            'message' => 'The '.$this->label($document).' was saved and expires on '.$document->expiry_date->toDateString().'.',
            'data' => $this->documents->row($document->setRelation('vehicle', $vehicle)),
        ], 201);
    }

    public function update(VehicleDocumentRequest $request, string $document): JsonResponse
    {
        $document = $this->find($document);

        $data = $request->validated();
        // A new expiry date starts the alert cycle again.
        if (($data['expiry_date'] ?? null) !== $document->expiry_date->toDateString()) {
            $data['last_alert_stage'] = null;
        }
        $document->update($data);

        return response()->json([
            'message' => 'The '.$this->label($document).' was updated.',
            'data' => $this->documents->row($document),
        ]);
    }

    public function destroy(string $document): JsonResponse
    {
        $document = $this->find($document);
        $label = $this->label($document);
        $document->delete();

        return response()->json(['message' => "The {$label} was deleted."]);
    }

    /** A document, only if its vehicle belongs to the active school. */
    private function find(string $id): VehicleDocument
    {
        $document = VehicleDocument::query()->whereHas('vehicle')->with('vehicle')->findOrFail($id);

        return $document;
    }

    private function label(VehicleDocument $document): string
    {
        return str_replace('_', ' ', $document->type);
    }
}
