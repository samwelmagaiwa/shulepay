<?php

namespace App\Services\Transport;

use App\Models\SchoolNotification;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which vehicle documents are expired, about to expire, or missing.
 *
 * Only the newest document of each type counts for a vehicle: once insurance has
 * been renewed, the old policy stops raising alerts.
 */
class VehicleDocumentService
{
    /** Every vehicle in service should hold these. */
    public const REQUIRED_TYPES = ['insurance', 'road_licence'];

    /**
     * @return Collection<int, VehicleDocument> the current document per vehicle and type, with the vehicle loaded
     */
    public function current(): Collection
    {
        return VehicleDocument::query()
            ->with('vehicle')
            ->whereHas('vehicle', fn ($q) => $q->where('status', '!=', 'retired'))
            ->get()
            ->groupBy(fn (VehicleDocument $d) => $d->vehicle_id.'|'.$d->type)
            ->map(fn (Collection $group) => $group->sortByDesc(fn ($d) => $d->expiry_date->timestamp)->first())
            ->values();
    }

    /**
     * @return array{expired: array, expiring: array, missing: array, counts: array}
     */
    public function alerts(?Carbon $today = null): array
    {
        $today ??= now();
        $current = $this->current();

        $rows = $current
            ->map(fn (VehicleDocument $d) => $this->row($d, $today))
            ->filter(fn (array $row) => $row['status'] !== VehicleDocument::STATUS_VALID)
            ->sortBy('days_left')
            ->values();

        $missing = Vehicle::query()
            ->where('status', '!=', 'retired')
            ->with('documents:id,vehicle_id,type')
            ->get()
            ->flatMap(fn (Vehicle $vehicle) => collect(self::REQUIRED_TYPES)
                ->reject(fn (string $type) => $vehicle->documents->contains('type', $type))
                ->map(fn (string $type) => [
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $vehicle->plate_number,
                    'type' => $type,
                ]))
            ->values()
            ->all();

        $expired = $rows->where('status', VehicleDocument::STATUS_EXPIRED)->values()->all();
        $expiring = $rows->where('status', VehicleDocument::STATUS_EXPIRING)->values()->all();

        return [
            'expired' => $expired,
            'expiring' => $expiring,
            'missing' => $missing,
            'counts' => [
                'expired' => count($expired),
                'expiring' => count($expiring),
                'missing' => count($missing),
                'total' => count($expired) + count($expiring) + count($missing),
            ],
        ];
    }

    /** A document as the API shows it, with its live status. */
    public function row(VehicleDocument $document, ?Carbon $today = null): array
    {
        return [
            'id' => $document->id,
            'vehicle_id' => $document->vehicle_id,
            'plate_number' => $document->vehicle?->plate_number,
            'type' => $document->type,
            'reference_number' => $document->reference_number,
            'provider' => $document->provider,
            'issue_date' => $document->issue_date?->toDateString(),
            'expiry_date' => $document->expiry_date->toDateString(),
            'cost_cents' => $document->cost_cents,
            'notes' => $document->notes,
            'days_left' => $document->daysLeft($today),
            'status' => $document->status($today),
        ];
    }

    /**
     * Raise an in-app notice for every document that has crossed a new expiry
     * threshold since its last notice. Safe to run any number of times a day: a
     * document is only announced again when it reaches the next, tighter stage.
     *
     * @return int notices created
     */
    public function sendDueAlerts(?int $schoolId, ?Carbon $today = null): int
    {
        $today ??= now();
        $sent = 0;

        foreach ($this->current() as $document) {
            $stage = $this->stageFor($document, $today);

            if ($stage === null || ($document->last_alert_stage !== null && $stage >= $document->last_alert_stage)) {
                continue;
            }

            $row = $this->row($document, $today);
            $what = str_replace('_', ' ', $document->type);
            $title = $stage === 0
                ? "{$row['plate_number']}: {$what} has expired"
                : "{$row['plate_number']}: {$what} expires in {$row['days_left']} day(s)";
            $body = "The {$what} of vehicle {$row['plate_number']} "
                .($stage === 0 ? 'expired on ' : 'expires on ').$row['expiry_date'].'. Renew it and record the new document.';

            // The transport screens are for the owner and the accountant.
            foreach (['owner', 'accountant'] as $role) {
                SchoolNotification::create([
                    'school_id' => $schoolId,
                    'type' => 'vehicle_document_expiry',
                    'title' => $title,
                    'body' => $body,
                    'data' => ['vehicle_id' => $document->vehicle_id, 'document_id' => $document->id, 'stage' => $stage],
                    'recipient_role' => $role,
                    'is_read' => false,
                ]);
            }

            $document->update(['last_alert_stage' => $stage]);
            $sent++;
        }

        return $sent;
    }

    /** The alert stage a document has reached: 30, 14, 7 days out, or 0 once expired. */
    public function stageFor(VehicleDocument $document, ?Carbon $today = null): ?int
    {
        $days = $document->daysLeft($today);

        if ($days < 0) {
            return 0;
        }

        // The tightest threshold already crossed.
        $crossed = collect(VehicleDocument::ALERT_DAYS)->filter(fn (int $d) => $days <= $d)->min();

        return $crossed;
    }
}
