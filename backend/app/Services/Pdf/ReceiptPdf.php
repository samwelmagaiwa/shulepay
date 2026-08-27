<?php

namespace App\Services\Pdf;

use App\Models\Receipt;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdf
{
    public function generate(Receipt $receipt): string
    {
        // Everything the receipt prints, loaded up front so the Blade view never
        // triggers a lazy query (and never silently renders a blank particular).
        $receipt->loadMissing([
            'student.currentEnrollment.schoolClass',
            'student.currentEnrollment.school',
            'student.guardians',
            'payment.invoice.term',
            'payment.invoice.academicYear',
            'payment.invoice.lines',
            'payment.invoice.payments',
            'payment.recorder',
        ]);

        $school = $receipt->student?->school ?? $receipt->student?->currentEnrollment?->school;

        // Single source of truth for the letterhead — see App\Support\SchoolLetterhead.
        $lh = SchoolLetterhead::for($school);
        $appName = $lh['name'];
        $appTagline = $lh['tagline'];
        $logoBase64 = $lh['logo'];

        return Pdf::loadView('pdf.receipt', compact('receipt', 'appName', 'appTagline', 'logoBase64', 'lh'))
            // A5 (148mm × 210mm) — printed from the browser to office paper rather
            // than a thermal roll. Replaces the earlier 80mm strip, which left the
            // page mostly empty and the content squeezed into a narrow column.
            ->setPaper('a5')
            ->output();
    }
}
