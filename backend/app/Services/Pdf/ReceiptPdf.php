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
            // 80mm roll. Height raised from 160mm to 240mm so the fuller receipt
            // (particulars + running balance) fits on one page instead of spilling
            // onto a second. Switch to 'A5' here if printing to office paper.
            ->setPaper([0, 0, 226.77, 680.31]) // 80mm × 240mm thermal
            ->output();
    }
}
