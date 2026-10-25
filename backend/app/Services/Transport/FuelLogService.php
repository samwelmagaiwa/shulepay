<?php

namespace App\Services\Transport;

use App\Models\FuelLog;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Fuel logs and what can be worked out from them.
 *
 * Consumption uses the full-tank method: between two consecutive full-tank fills,
 * the distance driven divided by all the fuel put in after the first of them
 * (partial fills in between included) is the km per litre for that stretch.
 */
class FuelLogService
{
    /**
     * The odometer only ever goes up. A reading lower than an earlier log, or
     * higher than a later one, is a typing mistake — refuse it with the two
     * neighbouring readings so it is easy to correct.
     *
     * @throws TransportException
     */
    public function assertOdometerFits(Vehicle $vehicle, string $date, int $odometer, ?int $ignoreId = null): void
    {
        $others = FuelLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        $earlierHigher = (clone $others)->whereDate('fuelled_on', '<=', $date)->where('odometer_km', '>', $odometer)
            ->orderByDesc('odometer_km')->first();

        if ($earlierHigher) {
            throw TransportException::invalid(
                "The odometer reading {$odometer} km is lower than {$earlierHigher->odometer_km} km recorded on {$earlierHigher->fuelled_on->toDateString()}.",
                'ODOMETER_TOO_LOW',
                ['previous_km' => $earlierHigher->odometer_km],
            );
        }

        $laterLower = (clone $others)->whereDate('fuelled_on', '>', $date)->where('odometer_km', '<', $odometer)
            ->orderBy('odometer_km')->first();

        if ($laterLower) {
            throw TransportException::invalid(
                "The odometer reading {$odometer} km is higher than {$laterLower->odometer_km} km recorded later, on {$laterLower->fuelled_on->toDateString()}.",
                'ODOMETER_TOO_HIGH',
                ['next_km' => $laterLower->odometer_km],
            );
        }
    }

    /**
     * Every log of the vehicle, newest first, each with the consumption of the
     * stretch it closes (null when it cannot be worked out).
     *
     * @return Collection<int, array>
     */
    public function history(Vehicle $vehicle, ?string $from = null, ?string $to = null): Collection
    {
        $all = FuelLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('fuelled_on')->orderBy('odometer_km')->orderBy('id')
            ->get();

        $stretches = $this->stretches($all);

        return $all
            ->filter(fn (FuelLog $log) => (! $from || $log->fuelled_on->toDateString() >= $from) && (! $to || $log->fuelled_on->toDateString() <= $to))
            ->map(function (FuelLog $log) use ($stretches) {
                $stretch = $stretches[$log->id] ?? null;

                return $this->row($log) + [
                    'km_per_litre' => $stretch ? round($stretch['km'] / $stretch['litres'], 2) : null,
                    'stretch' => $stretch,
                ];
            })
            ->reverse()
            ->values();
    }

    /**
     * The stretch each full-tank fill closes.
     *
     * @param  Collection<int, FuelLog>  $logs  oldest first
     * @return array<int, array{km: int, litres: float, cost_cents: int}> log id => stretch
     */
    private function stretches(Collection $logs): array
    {
        $result = [];
        $lastFull = null;   // the previous full-tank log
        $litres = 0.0;      // fuel put in since that log
        $cost = 0;

        foreach ($logs as $log) {
            $litres += $log->litres;
            $cost += $log->cost_cents;

            if (! $log->full_tank) {
                continue;
            }

            if ($lastFull && $litres > 0 && $log->odometer_km > $lastFull->odometer_km) {
                $result[$log->id] = ['km' => $log->odometer_km - $lastFull->odometer_km, 'litres' => $litres, 'cost_cents' => $cost];
            }

            $lastFull = $log;
            $litres = 0.0;
            $cost = 0;
        }

        return $result;
    }

    /**
     * Totals for a vehicle over a period.
     *
     * @return array{fills: int, litres: float, cost_cents: int, avg_km_per_litre: ?float, cost_per_km_cents: ?int, km_driven: ?int}
     */
    public function summary(Vehicle $vehicle, ?string $from = null, ?string $to = null): array
    {
        $rows = $this->history($vehicle, $from, $to);

        return $this->totals($rows);
    }

    /** Fuel totals for every vehicle of the school, for a period. */
    public function fleet(?string $from = null, ?string $to = null): array
    {
        $vehicles = Vehicle::query()->orderBy('plate_number')->get();

        $perVehicle = $vehicles->map(function (Vehicle $vehicle) use ($from, $to) {
            return ['vehicle_id' => $vehicle->id, 'plate_number' => $vehicle->plate_number] + $this->summary($vehicle, $from, $to);
        })->filter(fn (array $row) => $row['fills'] > 0)->values();

        return [
            'vehicles' => $perVehicle->all(),
            'total' => [
                'fills' => $perVehicle->sum('fills'),
                'litres' => round($perVehicle->sum('litres'), 2),
                'cost_cents' => (int) $perVehicle->sum('cost_cents'),
            ],
        ];
    }

    private function totals(Collection $rows): array
    {
        $stretches = $rows->pluck('stretch')->filter();
        $km = (int) $stretches->sum('km');
        $stretchLitres = (float) $stretches->sum('litres');
        $odometers = $rows->pluck('odometer_km');

        return [
            'fills' => $rows->count(),
            'litres' => round((float) $rows->sum('litres'), 2),
            'cost_cents' => (int) $rows->sum('cost_cents'),
            // Weighted over every measured stretch, not an average of averages.
            'avg_km_per_litre' => $stretchLitres > 0 ? round($km / $stretchLitres, 2) : null,
            'cost_per_km_cents' => $km > 0 ? (int) round($stretches->sum('cost_cents') / $km) : null,
            'km_driven' => $rows->count() > 1 ? (int) ($odometers->max() - $odometers->min()) : null,
        ];
    }

    public function row(FuelLog $log): array
    {
        return [
            'id' => $log->id,
            'vehicle_id' => $log->vehicle_id,
            'fuelled_on' => $log->fuelled_on->toDateString(),
            'litres' => $log->litres,
            'cost_cents' => $log->cost_cents,
            'price_per_litre_cents' => $log->litres > 0 ? (int) round($log->cost_cents / $log->litres) : null,
            'odometer_km' => $log->odometer_km,
            'full_tank' => $log->full_tank,
            'station' => $log->station,
            'driver_name' => $log->driver_name,
            'notes' => $log->notes,
        ];
    }
}
