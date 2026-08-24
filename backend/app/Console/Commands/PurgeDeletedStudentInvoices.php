<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the billing records belonging to soft-deleted students.
 *
 * When a student is soft-deleted the invoices and payments stay behind. They no
 * longer resolve to a student in the UI (blank Student/Class cells) but still
 * count toward dashboard totals such as "Collected".
 *
 * This is destructive and irreversible: payments are real financial records.
 * Always run --dry-run first, and take a database dump before the real run.
 */
class PurgeDeletedStudentInvoices extends Command
{
    protected $signature = 'students:purge-deleted-invoices
                            {--dry-run : List what would be removed without deleting}
                            {--student= : Restrict to a single student id}';

    protected $description = 'Delete invoices/payments belonging to soft-deleted students';

    public function handle(): int
    {
        $students = DB::table('students')->whereNotNull('deleted_at')
            ->when($this->option('student'), fn ($q, $id) => $q->where('id', (int) $id))
            ->get(['id', 'first_name', 'last_name', 'deleted_at']);

        if ($students->isEmpty()) {
            $this->info('No soft-deleted students found. Nothing to do.');

            return self::SUCCESS;
        }

        $studentIds = $students->pluck('id')->all();
        $invoices = DB::table('invoices')->whereIn('student_id', $studentIds)->get();
        $invoiceIds = $invoices->pluck('id')->all();

        $payments = DB::table('payments')->whereIn('invoice_id', $invoiceIds)->get();
        $paymentIds = $payments->pluck('id')->all();

        // restrictOnDelete — a refund would block the delete, so surface it up front.
        $refunds = $paymentIds
            ? DB::table('refunds')->whereIn('payment_id', $paymentIds)->count()
            : 0;

        $this->newLine();
        $this->line('Soft-deleted students: '.$students->count());
        foreach ($students as $s) {
            $n = count(array_filter($invoices->all(), fn ($i) => $i->student_id === $s->id));
            $this->line(sprintf('  [%d] %-24s deleted %s  invoices=%d',
                $s->id, trim("$s->first_name $s->last_name"), $s->deleted_at, $n));
        }

        $this->newLine();
        $this->line('Records to delete:');
        $this->line('  invoices          '.count($invoiceIds));
        $this->line('  payments          '.count($paymentIds).'  (TZS '.number_format($payments->sum('amount_cents') / 100).')');
        $this->line('  invoice_lines     '.($invoiceIds ? DB::table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->count() : 0));
        $this->line('  installment_plans '.($invoiceIds ? DB::table('installment_plans')->whereIn('invoice_id', $invoiceIds)->count() : 0));
        $this->line('  payment_promises  '.($invoiceIds ? DB::table('payment_promises')->whereIn('invoice_id', $invoiceIds)->count() : 0));
        $this->line('  receipts          '.($paymentIds ? DB::table('receipts')->whereIn('id', $payments->pluck('receipt_id')->filter()->all())->count() : 0));
        $this->newLine();

        if ($refunds > 0) {
            $this->error("{$refunds} refund(s) reference these payments (restrictOnDelete).");
            $this->error('Resolve those refunds first — aborting so nothing is half-deleted.');

            return self::FAILURE;
        }

        if (empty($invoiceIds)) {
            $this->info('These students have no invoices. Nothing to delete.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No changes written.');

            return self::SUCCESS;
        }

        $this->warn('This permanently deletes real payment records and cannot be undone.');
        if ($this->input->isInteractive() && ! $this->confirm('Proceed with deletion?', false)) {
            $this->info('Aborted. Nothing changed.');

            return self::SUCCESS;
        }

        $receiptIds = $payments->pluck('receipt_id')->filter()->all();

        DB::transaction(function () use ($invoiceIds, $paymentIds, $receiptIds, $studentIds, $payments) {
            // Order matters: payments hold a restrictOnDelete FK to invoices, so they
            // must go first. Receipts are only reachable once their payment is gone.
            DB::table('payments')->whereIn('id', $paymentIds)->delete();
            if ($receiptIds) {
                DB::table('receipts')->whereIn('id', $receiptIds)->delete();
            }
            DB::table('invoice_lines')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('installment_plans')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('payment_promises')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('discounts')->whereIn('invoice_id', $invoiceIds)->update(['invoice_id' => null]);
            DB::table('invoices')->whereIn('id', $invoiceIds)->delete();

            // A deleted student's enrollment must not stay 'active' — it inflates
            // student counts and holds on to an admission number.
            DB::table('enrollments')->whereIn('student_id', $studentIds)
                ->where('status', 'active')->update(['status' => 'dropped']);

            // Keep a record of exactly what was removed — this is irreversible.
            AuditLog::record('deleted_student_invoices_purged', null, [
                'student_ids' => array_values($studentIds),
                'invoice_ids' => array_values($invoiceIds),
                'payment_ids' => array_values($paymentIds),
                'amount_cents' => $payments->sum('amount_cents'),
            ], []);
        });

        $this->newLine();
        $this->info('Deleted '.count($invoiceIds).' invoice(s) and '.count($paymentIds).' payment(s).');
        $this->line('Remaining invoices: '.DB::table('invoices')->count());
        $this->line('Remaining payments: '.DB::table('payments')->count());

        return self::SUCCESS;
    }
}
