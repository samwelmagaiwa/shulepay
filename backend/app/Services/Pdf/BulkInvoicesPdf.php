<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Every invoice matching a status filter (Partial / Unpaid) printed as one
 * document — the Invoices page's "Print by status" action, for a
 * bookkeeper who wants every debtor's slip in hand at once instead of
 * printing one student's receipt at a time.
 *
 * One page per invoice via CSS page-break, not a merge of separate PDFs —
 * same DomPDF-single-view approach StudentStatementPdf uses for one
 * student's many invoices, just one invoice per page here instead of many
 * invoices per student section.
 */
class BulkInvoicesPdf
{
    /** @param  Collection<int, Invoice>  $invoices */
    public function generate(Collection $invoices, string $status): string
    {
        // The default 128M limit crashes DomPDF partway through a large batch
        // (confirmed: 257 invoices exhausted it outright) — each page repeats
        // a base64-embedded logo, and DomPDF holds the whole render tree in
        // memory at once. Scoped to this request only, not the php.ini
        // default, and restored afterward so nothing else on the same
        // PHP-FPM worker inherits a raised ceiling it never asked for.
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            return $this->render($invoices, $status);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    /** @param  Collection<int, Invoice>  $invoices */
    private function render(Collection $invoices, string $status): string
    {
        // "Shule Zote" (all schools) mode can mix invoices from more than one
        // school in the same batch — each page must carry ITS OWN letterhead,
        // not whichever school happened to load first.
        $letterheadCache = [];

        $rows = $invoices->map(function (Invoice $inv) use (&$letterheadCache) {
            $schoolId = $inv->school_id;
            if (! isset($letterheadCache[$schoolId])) {
                $letterheadCache[$schoolId] = SchoolLetterhead::for($inv->school);
            }

            $enrollment = $inv->student?->currentEnrollment;
            $gross = $inv->total_amount_cents->cents()
                + $inv->arrears_cents->cents()
                - $inv->discount_cents->cents();
            $paid = $inv->paidCents();

            return (object) [
                'lh' => $letterheadCache[$schoolId],
                'student' => $inv->student,
                'enrollment' => $enrollment,
                'guardian' => $inv->student?->guardians?->first(),
                'invoice_number' => $inv->invoice_number,
                'term' => $inv->term?->name,
                'academic_year' => $inv->academicYear?->name,
                'gross_cents' => $gross,
                'paid_cents' => $paid,
                'balance_cents' => max(0, $gross - $paid),
                'status' => $inv->status instanceof \BackedEnum ? $inv->status->value : $inv->status,
            ];
        });

        return Pdf::loadView('pdf.bulk_invoices', [
            'rows' => $rows,
            'statusFilter' => $status,
            'generatedAt' => now(),
        ])->setPaper('a4')->output();
    }
}
