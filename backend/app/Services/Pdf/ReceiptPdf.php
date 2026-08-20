<?php

namespace App\Services\Pdf;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptPdf
{
    public function generate(Receipt $receipt): string
    {
        $school   = $receipt->student?->school ?? $receipt->student?->currentEnrollment?->school;
        $settings = $school?->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $appName    = $branding['app_name']    ?? ($school?->name ?? 'ShulePay');
        $appTagline = $branding['app_tagline'] ?? 'nexoryaTECH';
        $logoBase64 = null;

        if (isset($branding['logo_path']) && Storage::exists($branding['logo_path'])) {
            $mime = Storage::mimeType($branding['logo_path']);
            $data = base64_encode(Storage::get($branding['logo_path']));
            $logoBase64 = "data:{$mime};base64,{$data}";
        }

        return Pdf::loadView('pdf.receipt', compact('receipt', 'appName', 'appTagline', 'logoBase64'))
            ->setPaper([0, 0, 226.77, 453.54]) // 80mm × 160mm thermal
            ->output();
    }
}
