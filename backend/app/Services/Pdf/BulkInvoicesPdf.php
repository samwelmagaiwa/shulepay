<?php

namespace App\Services\Pdf;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Every student who has at least one invoice matching a status filter
 * (Partial / Unpaid) printed as one document — the Invoices page's
 * "Print by status" action. Each student's section is their FULL
 * consolidated statement (every term, every invoice, any status), the
 * exact same design as the single "Print Receipt" action produces —
 * built via StudentStatementPdf::buildSection() so both stay identical
 * — not just the invoice(s) that matched the filter.
 */
class BulkInvoicesPdf
{
    public function __construct(private StudentStatementPdf $statementPdf) {}

    /** @param  Collection<int, Student>  $students */
    public function generate(Collection $students, string $status): string
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
            return $this->render($students, $status);
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    /** @param  Collection<int, Student>  $students */
    private function render(Collection $students, string $status): string
    {
        $sections = $students->map(fn (Student $student) => $this->statementPdf->buildSection($student));

        return Pdf::loadView('pdf.bulk_invoices', [
            'sections' => $sections,
            'statusFilter' => $status,
            'generatedAt' => now(),
        ])->setPaper('a4')->output();
    }
}
