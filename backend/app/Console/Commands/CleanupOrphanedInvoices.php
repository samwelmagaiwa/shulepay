<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedInvoices extends Command
{
    protected $signature = 'invoices:cleanup-orphaned {--force : Actually delete. Without this flag, only reports what would be removed.}';

    protected $description = 'Find and optionally remove invoices whose student was deleted (soft-deleted). Deleting the student does not cascade to invoices/payments since it is a soft delete, not a real SQL DELETE — this leaves orphaned financial records behind. This is PERMANENT and irreversible for the affected invoices and their payments.';

    public function handle(): int
    {
        $force = $this->option('force');

        $orphaned = Invoice::allSchools()
            ->whereDoesntHave('student') // student relation excludes soft-deleted students by default
            ->withCount('payments')
            ->with('lines')
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info('No orphaned invoices found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->warn("Found {$orphaned->count()} orphaned invoice(s) (student was deleted):");
        $this->table(
            ['Invoice ID', 'Invoice Number', 'Student ID', 'School ID', 'Total (TZS)', 'Payments'],
            $orphaned->map(fn ($inv) => [
                $inv->id,
                $inv->invoice_number,
                $inv->student_id,
                $inv->school_id,
                number_format($inv->total_amount_cents->cents() / 100),
                $inv->payments_count,
            ])
        );

        if (! $force) {
            $this->line('');
            $this->line('DRY RUN — nothing was deleted. Re-run with --force to permanently remove');
            $this->line('these invoices and their payment records. This cannot be undone.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->warn('--force given: permanently deleting the invoices listed above and their payments...');

        DB::transaction(function () use ($orphaned) {
            foreach ($orphaned as $invoice) {
                // Payments use SoftDeletes, but payments.invoice_id has a restrictOnDelete
                // FK — a soft delete alone would leave the row in place and still block
                // deleting the invoice. Force-delete to actually free the constraint.
                Payment::allSchools()->withTrashed()
                    ->where('invoice_id', $invoice->id)
                    ->get()
                    ->each(fn (Payment $p) => $p->forceDelete());

                // invoice_lines cascade automatically; discounts are nulled automatically
                // (both declared at the DB FK level) — no manual cleanup needed for those.
                $invoice->delete();
            }
        });

        $this->info("Deleted {$orphaned->count()} orphaned invoice(s) and their payment records.");

        return self::SUCCESS;
    }
}
