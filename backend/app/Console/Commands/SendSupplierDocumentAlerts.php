<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\Suppliers\SupplierNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily: tell the owner and accountant about supplier documents (tax clearance, licence,
 * contract...) that are 30, 14 or 7 days from expiring, or already expired.
 * In-app notices only — no SMS is sent.
 */
class SendSupplierDocumentAlerts extends Command
{
    protected $signature = 'suppliers:document-alerts';

    protected $description = 'Notify staff about supplier documents that are expiring or expired';

    public function handle(): int
    {
        $total = 0;

        foreach (School::all() as $school) {
            try {
                // The active school scopes the suppliers to this school.
                app()->instance('active_school', $school);

                $sent = SupplierNotifier::sendDueDocumentAlerts();
                $total += $sent;
                if ($sent) {
                    $this->info("{$school->name}: {$sent} supplier document alert(s) sent.");
                }
            } catch (\Throwable $e) {
                // One school's problem must not stop the rest.
                Log::warning("[SupplierDocumentAlerts] {$school->name}: {$e->getMessage()}");
                $this->warn("{$school->name}: skipped ({$e->getMessage()})");
            } finally {
                app()->forgetInstance('active_school');
            }
        }

        $this->info("Done. {$total} alert(s) sent.");

        return self::SUCCESS;
    }
}
