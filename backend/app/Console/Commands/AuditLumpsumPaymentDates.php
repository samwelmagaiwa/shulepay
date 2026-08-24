<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class AuditLumpsumPaymentDates extends Command
{
    protected $signature = 'payments:audit-lumpsum-dates';

    protected $description = 'Read-only report: lists Annual Summary (lumpsum) migrated payments whose paid_at was hardcoded to the day they were entered (fixed in 6bfcde3), rather than the true historical payment date. Makes no changes.';

    public function handle(): int
    {
        $payments = Payment::allSchools() // audit across every school, not just whatever is bound in this CLI context
            ->where('notes', 'Imehamishwa kutoka vitabuni - Jumla ya malipo ya juu')
            // paid_at was stored via now()->toDateString() (midnight), so compare
            // calendar dates only — an exact datetime match would never hit since
            // created_at carries a real time-of-day.
            ->whereRaw('DATE(paid_at) = DATE(created_at)')
            ->with('student:id,first_name,last_name')
            ->orderBy('created_at')
            ->get(['id', 'student_id', 'school_id', 'amount_cents', 'paid_at', 'created_at']);

        if ($payments->isEmpty()) {
            $this->info('No affected records found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$payments->count()} lumpsum-migrated payment(s) dated to their entry day (likely incorrect):");
        $this->table(
            ['Payment ID', 'Student', 'School ID', 'Amount (TZS)', 'paid_at (wrong)', 'created_at'],
            $payments->map(fn ($p) => [
                $p->id,
                $p->student ? "{$p->student->first_name} {$p->student->last_name}" : "#{$p->student_id}",
                $p->school_id,
                number_format($p->amount_cents->cents() / 100),
                $p->paid_at->toDateString(),
                $p->created_at->toDateTimeString(),
            ])
        );

        $this->line('');
        $this->line('This command makes NO changes. It only reports affected records so a correct');
        $this->line('historical paid_at can be confirmed per-record before anything is corrected.');

        return self::SUCCESS;
    }
}
