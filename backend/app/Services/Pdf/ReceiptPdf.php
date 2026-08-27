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
            // A4 — the paper actually loaded in the school's printer. An A5 page
            // sent to an A4 tray prints as a small block in one corner.
            ->setPaper('a4')
            ->output();
    }
}
