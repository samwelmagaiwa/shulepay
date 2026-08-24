<?php

namespace App\Services\Pdf;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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
        $settings = $school?->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $appName = $branding['app_name'] ?? ($school?->name ?? 'ShulePay');
        $appTagline = $branding['app_tagline'] ?? 'nexoryaTECH';
        $logoBase64 = null;

        if (isset($branding['logo_path']) && Storage::exists($branding['logo_path'])) {
            $mime = Storage::mimeType($branding['logo_path']);
            $data = base64_encode(Storage::get($branding['logo_path']));
            $logoBase64 = "data:{$mime};base64,{$data}";
        }

        return Pdf::loadView('pdf.receipt', compact('receipt', 'appName', 'appTagline', 'logoBase64'))
            // A5 (148mm × 210mm) — printed from the browser to office paper rather
            // than a thermal roll. Replaces the earlier 80mm strip, which left the
            // page mostly empty and the content squeezed into a narrow column.
            ->setPaper('a5')
            ->output();
    }
}
