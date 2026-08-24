<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\ReceiptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Payments imported during student migration were created directly via Payment::create()
 * and never got a receipt, so the receipt button stays hidden for them forever. This
 * issues a receipt for each such payment.
 *
 * Additive only: it sets receipt_id where it is currently NULL and touches nothing else.
 */
class BackfillPaymentReceipts extends Command
{
    protected $signature = 'payments:backfill-receipts {--dry-run : Report what would change without writing}';

    protected $description = 'Issue receipts for payments that have none (e.g. migrated from books)';

    public function handle(ReceiptService $receipts): int
    {
        $query = Payment::withoutGlobalScopes()->whereNull('receipt_id');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No payments are missing a receipt. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[dry-run] {$total} payment(s) would receive a receipt. No changes written.");
            $this->table(
                ['payment_id', 'student_id', 'amount_cents', 'paid_at'],
                (clone $query)->orderBy('id')->limit(20)
                    ->get(['id', 'student_id', 'amount_cents', 'paid_at'])
                    ->map(fn ($p) => [
                        $p->id,
                        $p->student_id,
                        (int) $p->getRawOriginal('amount_cents'),
                        (string) $p->paid_at,
                    ])->all()
            );

            if ($total > 20) {
                $this->line('... and '.($total - 20).' more.');
            }

            return self::SUCCESS;
        }

        $this->info("Issuing receipts for {$total} payment(s)...");
        $bar = $this->output->createProgressBar($total);
        $done = 0;

        // Chunk by id so the receipt_id writes cannot shift the result set mid-iteration.
        (clone $query)->orderBy('id')->chunkById(100, function ($payments) use ($receipts, &$done, $bar) {
            foreach ($payments as $payment) {
                DB::transaction(function () use ($receipts, $payment) {
                    $receipt = $receipts->issue($payment->student_id);
                    $payment->forceFill(['receipt_id' => $receipt->id])->saveQuietly();
                });
                $done++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $remaining = Payment::withoutGlobalScopes()->whereNull('receipt_id')->count();
        $this->info("Done. {$done} receipt(s) issued. Payments still without a receipt: {$remaining}.");

        return self::SUCCESS;
    }
}
