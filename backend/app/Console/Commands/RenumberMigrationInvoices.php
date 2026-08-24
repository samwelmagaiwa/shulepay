<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renumbers old-format migration invoices (MIG-{year}-{seq}) to the current
 * format (MIG/{school code}/{seq}/{year}), so every migration invoice — old
 * and new — follows the same scheme. Renumbers only, no financial data
 * (amounts, dates, payments) is touched.
 */
class RenumberMigrationInvoices extends Command
{
    protected $signature = 'invoices:renumber-migration {--force : Actually rename. Without this flag, only reports what would change.}';

    protected $description = 'Rename old MIG-YYYY-NNNNNN invoice numbers to MIG/{school code}/NNNN/YYYY, matching the format new migration invoices already use.';

    public function handle(): int
    {
        $force = $this->option('force');

        $old = Invoice::allSchools()
            ->where('invoice_number', 'like', 'MIG-%')
            ->orderBy('school_id')
            ->orderBy('id')
            ->get(['id', 'school_id', 'invoice_number', 'created_at']);

        if ($old->isEmpty()) {
            $this->info('No old-format migration invoice numbers found. Nothing to do.');

            return self::SUCCESS;
        }

        $schools = School::whereIn('id', $old->pluck('school_id')->unique())->get()->keyBy('id');

        // Per-school starting sequence: continue after any invoice already using
        // the new format, so renumbering never collides with one just created.
        $nextSeq = [];
        foreach ($old->pluck('school_id')->unique() as $schoolId) {
            $code = strtoupper($schools[$schoolId]->code ?? 'SCH');
            $year = date('Y');
            $max = Invoice::allSchools()
                ->where('school_id', $schoolId)
                ->where('invoice_number', 'like', "MIG/{$code}/%/{$year}")
                ->get(['invoice_number'])
                ->map(fn ($inv) => preg_match('#^MIG/'.$code.'/(\d+)/'.$year.'$#', $inv->invoice_number, $m) ? (int) $m[1] : 0)
                ->max();
            $nextSeq[$schoolId] = ((int) $max) + 1;
        }

        $plan = $old->map(function ($inv) use ($schools, &$nextSeq) {
            $school = $schools[$inv->school_id];
            $code = strtoupper($school->code ?? 'SCH');
            $year = date('Y', strtotime($inv->created_at));
            $seq = $nextSeq[$inv->school_id]++;
            $new = sprintf('MIG/%s/%04d/%s', $code, $seq, $year);

            return ['id' => $inv->id, 'old' => $inv->invoice_number, 'new' => $new, 'school' => $school->name];
        });

        $this->warn("Renumbering {$plan->count()} invoice(s):");
        $this->table(['Invoice ID', 'School', 'Old Number', 'New Number'],
            $plan->map(fn ($p) => [$p['id'], $p['school'], $p['old'], $p['new']]));

        if (! $force) {
            $this->line('');
            $this->line('DRY RUN — nothing changed. Re-run with --force to apply. Only invoice_number');
            $this->line('is changed — amounts, dates, payments, and receipts are untouched.');

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
