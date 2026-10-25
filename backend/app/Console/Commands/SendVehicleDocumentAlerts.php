<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Transport\VehicleDocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily: tell the owner and accountant about vehicle documents (insurance, road
 * licence...) that are 30, 14 or 7 days from expiring, or already expired.
 * In-app notices only — no SMS is sent.
 */
class SendVehicleDocumentAlerts extends Command
{
    protected $signature = 'transport:document-alerts';

    protected $description = 'Notify staff about vehicle documents that are expiring or expired';

    public function handle(VehicleDocumentService $documents): int
    {
        $total = 0;

        foreach (School::all() as $school) {
            try {
                // The active school scopes the vehicles to this school.
                app()->instance('active_school', $school);

                $sent = $documents->sendDueAlerts($school->id);
                $total += $sent;

                if ($sent) {
                    $this->info("{$school->name}: {$sent} vehicle document alert(s) sent.");
                }
            } catch (\Throwable $e) {
                // One school's problem must not stop the rest.
                Log::warning("[VehicleDocumentAlerts] {$school->name}: {$e->getMessage()}");
                $this->warn("{$school->name}: skipped ({$e->getMessage()})");
            } finally {
                app()->forgetInstance('active_school');
            }
        }

        $this->info("Done. {$total} alert(s) sent.");

        return self::SUCCESS;
    }
}
