<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renumbers old-format migration invoices (MIG-{year}-{seq}) by swapping the
 * "MIG" prefix for the owning school's code (MIG-2026-000020 becomes
 * MGRTHMR-2026-000020 for secondary, MGRTH-2026-000020 for primary). The
 * year and sequence digits are kept exactly as they are — only the prefix
 * changes. No financial data (amounts, dates, payments) is touched.
 */
class RenumberMigrationInvoices extends Command
{
    protected $signature = 'invoices:renumber-migration {--force : Actually rename. Without this flag, only reports what would change.}';

    protected $description = 'Swap the MIG- prefix on old migration invoice numbers for the school code (e.g. MIG-2026-000020 -> MGRTHMR-2026-000020).';

    public function handle(): int
    {
        $force = $this->option('force');

        $old = Invoice::allSchools()
            ->where('invoice_number', 'like', 'MIG-%')
            ->orderBy('school_id')
            ->orderBy('id')
            ->get(['id', 'school_id', 'invoice_number']);

        if ($old->isEmpty()) {
            $this->info('No old-format migration invoice numbers found. Nothing to do.');

            return self::SUCCESS;
        }

        $schools = School::whereIn('id', $old->pluck('school_id')->unique())->get()->keyBy('id');

        $plan = $old->map(function ($inv) use ($schools) {
            $code = strtoupper($schools[$inv->school_id]->code ?? 'SCH');
            // Swap only the leading "MIG-" — the rest of the number (year-seq) is untouched.
            $new = preg_replace('/^MIG-/', "{$code}-", $inv->invoice_number);

            return ['id' => $inv->id, 'old' => $inv->invoice_number, 'new' => $new, 'school' => $schools[$inv->school_id]->name];
        });

        $this->warn("Renumbering {$plan->count()} invoice(s):");
        $this->table(['Invoice ID', 'School', 'Old Number', 'New Number'],
            $plan->map(fn ($p) => [$p['id'], $p['school'], $p['old'], $p['new']]));

        if (! $force) {
            $this->line('');
            $this->line('DRY RUN — nothing changed. Re-run with --force to apply. Only the "MIG" prefix');
            $this->line('is replaced with the school code — year and sequence digits are unchanged.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $p) {
                Invoice::allSchools()->where('id', $p['id'])->update(['invoice_number' => $p['new']]);
            }
        });

        $this->info("Renamed {$plan->count()} invoice number(s).");

        return self::SUCCESS;
    }
}
