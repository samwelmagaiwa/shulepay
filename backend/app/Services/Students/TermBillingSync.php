<?php

namespace App\Services\Students;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Services\Payments\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Term-by-term billing for a student who is already registered.
 *
 * Registration imports a student's pre-ShulePay history once
 * (StudentRegistrationService::importPaymentHistory), creating an invoice per
 * term. Afterwards the figures still need correcting — a term was never
 * invoiced, a fee was typed wrong, an instalment was recorded short — and that
 * is what this does: it UPDATES what exists and only creates what is missing,
 * so editing a student can never duplicate their invoices or payments.
 *
 * Deliberately absent: deleting. Removing an invoice or reversing a payment
 * stays on the student page, where reversal is superadmin-only and audited; an
 * edit form that silently dropped a payment would be a hole in the records.
 */
class TermBillingSync
{
    /**
     * Every term of the student's academic years, with its invoice and payments
     * when one exists. Terms with no invoice are returned too, so the form can
     * offer the missing one (the usual reason for opening it).
     *
     * @return array{terms: list<array<string, mixed>>}
     */
    public function snapshot(Student $student, int $schoolId): array
    {
        $invoices = Invoice::withoutGlobalScope('school')
            ->where('student_id', $student->id)
            ->where('school_id', $schoolId)
            ->with(['payments' => fn ($q) => $q->orderBy('paid_at'), 'term', 'academicYear', 'lines'])
            ->get()
            ->keyBy('term_id');

        // The terms on offer are those of the years the student is billed or
        // enrolled in — not every year the school has ever run.
        $yearIds = $invoices->pluck('academic_year_id')
            ->merge($student->enrollments()->where('school_id', $schoolId)->pluck('academic_year_id'))
            ->filter()->unique()->values();

        $terms = Term::query()
            ->whereIn('academic_year_id', $yearIds)
            ->with('academicYear:id,name')
            ->orderBy('academic_year_id')
            ->orderBy('number')
            ->get();

        $rows = $terms->map(function (Term $term) use ($invoices) {
            $invoice = $invoices->get($term->id);

            return [
                'term_id' => $term->id,
                'term_name' => $term->name,
                'term_number' => $term->number,
                // The term's own dates, so the form shows the period being billed
                // exactly as the registration wizard does.
                'start_date' => optional($term->start_date)->toDateString(),
                'end_date' => optional($term->end_date)->toDateString(),
                'academic_year_id' => $term->academic_year_id,
                'academic_year_name' => $term->academicYear?->name,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                // Itemised invoices carry one line per fee item; their total is the
                // sum of those lines, so the form must not offer a single figure.
                'itemised' => $invoice ? $invoice->lines->count() > 1 : false,
                'fee_amount_cents' => $invoice ? (int) $invoice->getRawOriginal('total_amount_cents') : 0,
                'arrears_cents' => $invoice ? (int) $invoice->getRawOriginal('arrears_cents') : 0,
                'discount_cents' => $invoice ? (int) $invoice->getRawOriginal('discount_cents') : 0,
                'paid_cents' => $invoice ? (int) $invoice->payments->sum(fn ($p) => (int) $p->getRawOriginal('amount_cents')) : 0,
                'status' => $invoice?->status instanceof \BackedEnum ? $invoice->status->value : $invoice?->status,
                'payments' => $invoice ? $invoice->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount_cents' => (int) $p->getRawOriginal('amount_cents'),
                    'paid_at' => optional($p->paid_at)->toDateString() ?? substr((string) $p->paid_at, 0, 10),
                    'method' => $p->method instanceof \BackedEnum ? $p->method->value : $p->method,
                    'reference_number' => $p->reference_number,
                ])->values()->all() : [],
            ];
        });

        return ['terms' => $rows->values()->all()];
    }

    /**
     * Apply the edited terms.
     *
     * @param  list<array<string, mixed>>  $terms
     * @return array{invoices_created: int, invoices_updated: int, payments_created: int, payments_updated: int}
     */
    public function sync(Student $student, int $schoolId, array $terms): array
    {
        $result = ['invoices_created' => 0, 'invoices_updated' => 0, 'payments_created' => 0, 'payments_updated' => 0];

        return DB::transaction(function () use ($student, $schoolId, $terms, &$result) {
            foreach ($terms as $entry) {
                $termId = (int) ($entry['term_id'] ?? 0);
                $yearId = (int) ($entry['academic_year_id'] ?? 0);
                $feeCents = (int) ($entry['fee_amount_cents'] ?? 0);
                $payments = $entry['payments'] ?? [];

                if (! $termId || ! $yearId) {
                    continue;
                }

                $invoice = Invoice::withoutGlobalScope('school')
                    ->where('student_id', $student->id)
                    ->where('school_id', $schoolId)
                    ->where('term_id', $termId)
                    ->with(['lines', 'payments'])
                    ->first();

                // A term left at zero with no invoice is simply not billed; it is
                // the default state of the form's empty rows, not a request.
                if (! $invoice && $feeCents <= 0 && empty($payments)) {
                    continue;
                }

                if (! $invoice) {
                    $invoice = $this->createInvoice($student, $schoolId, $termId, $yearId, $feeCents);
                    $result['invoices_created']++;
                } elseif ($feeCents > 0) {
                    $this->updateInvoiceTotal($invoice, $feeCents, $result);
                }

                $this->syncPayments($invoice, $student, $schoolId, $payments, $result);
                $invoice->refresh()->syncStatus();
            }

            return $result;
        });
    }

    private function createInvoice(Student $student, int $schoolId, int $termId, int $yearId, int $feeCents): Invoice
    {
        if ($feeCents <= 0) {
            throw ValidationException::withMessages([
                'terms' => __('A term being added needs a fee amount.'),
            ]);
        }

        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id,
            'school_id' => $schoolId,
            'term_id' => $termId,
            'academic_year_id' => $yearId,
            'invoice_number' => $this->nextInvoiceNumber($schoolId),
            'total_amount_cents' => $feeCents,
            'arrears_cents' => 0,
            'discount_cents' => 0,
            'status' => 'unpaid',
            'due_date' => null,
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ]);

        $invoice->lines()->create([
            'fee_item_id' => null,
            'description' => 'Ada ya muhula',
            'amount_cents' => $feeCents,
        ]);

        return $invoice->load(['lines', 'payments']);
    }

    private function updateInvoiceTotal(Invoice $invoice, int $feeCents, array &$result): void
    {
        $current = (int) $invoice->getRawOriginal('total_amount_cents');
        if ($current === $feeCents) {
            return;
        }

        // An itemised invoice's total is the sum of its fee items; rewriting it
        // from one figure would leave the lines contradicting the total.
        if ($invoice->lines->count() > 1) {
            throw ValidationException::withMessages([
                'terms' => __('Invoice :number is itemised, so its total must be changed on the invoice itself.', [
                    'number' => $invoice->invoice_number,
                ]),
            ]);
        }

        $paid = (int) $invoice->payments->sum(fn ($p) => (int) $p->getRawOriginal('amount_cents'));
        if ($feeCents < $paid) {
            throw ValidationException::withMessages([
                'terms' => __('Invoice :number already has :paid paid, so its total cannot be lower.', [
                    'number' => $invoice->invoice_number,
                    'paid' => number_format($paid / 100),
                ]),
            ]);
        }

        $invoice->update(['total_amount_cents' => $feeCents]);

        $line = $invoice->lines->first();
        if ($line) {
            $line->update(['amount_cents' => $feeCents]);
        } else {
            $invoice->lines()->create([
                'fee_item_id' => null,
                'description' => 'Ada ya muhula',
                'amount_cents' => $feeCents,
            ]);
        }

        $result['invoices_updated']++;
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function syncPayments(Invoice $invoice, Student $student, int $schoolId, array $payments, array &$result): void
    {
        $existing = $invoice->payments()->get()->keyBy('id');
        $billable = (int) $invoice->getRawOriginal('total_amount_cents')
            + (int) $invoice->getRawOriginal('arrears_cents')
            - (int) $invoice->getRawOriginal('discount_cents');

        // Payments the form did not touch still count against the invoice total.
        $touchedIds = collect($payments)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $running = (int) $existing->whereNotIn('id', $touchedIds)->sum(fn ($p) => (int) $p->getRawOriginal('amount_cents'));

        foreach ($payments as $row) {
            $amount = (int) ($row['amount_cents'] ?? 0);
            $paidAt = $row['paid_at'] ?? null;
            if ($amount <= 0 || ! $paidAt) {
                continue; // an empty row in the form, not a payment
            }

            $running += $amount;
            if ($running > $billable) {
                throw ValidationException::withMessages([
                    'terms' => __('Payments for invoice :number would exceed its total.', [
                        'number' => $invoice->invoice_number,
                    ]),
                ]);
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id && $existing->has($id)) {
                $existing[$id]->update([
                    'amount_cents' => $amount,
                    'paid_at' => $paidAt,
                    'method' => $row['method'] ?? $existing[$id]->method,
                    'reference_number' => $row['reference_number'] ?? $existing[$id]->reference_number,
                ]);
                $result['payments_updated']++;

                continue;
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'school_id' => $schoolId,
                // Without a receipt the print button is permanently unavailable for
                // this payment, exactly as for migrated ones.
                'receipt_id' => app(ReceiptService::class)->issue($student->id)->id,
                'amount_cents' => $amount,
                'method' => $row['method'] ?? 'cash',
                'reference_number' => $row['reference_number'] ?? null,
                'paid_at' => $paidAt,
                'recorded_by' => auth()->id(),
                'notes' => trim($row['notes'] ?? '') ?: 'Imeongezwa wakati wa kuhariri mwanafunzi',
            ]);
            $result['payments_created']++;
        }
    }

    /**
     * Same numbering as the migration import: {code}-{year}-{000001}, scoped to
     * the school id so renaming a school's code never resets the sequence.
     */
    private function nextInvoiceNumber(int $schoolId): string
    {
        $year = date('Y');
        $code = strtoupper(School::find($schoolId)?->code ?? 'SCH');

        $seq = Invoice::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('invoice_number', 'like', "{$code}-{$year}-%")
            ->get(['invoice_number'])
            ->map(fn ($inv) => preg_match('#^'.preg_quote($code, '#').'-'.$year.'-(\d+)$#', $inv->invoice_number, $m) ? (int) $m[1] : 0)
            ->max();

        return sprintf('%s-%s-%06d', $code, $year, ((int) $seq) + 1);
    }
}
