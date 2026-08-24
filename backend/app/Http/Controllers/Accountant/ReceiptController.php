<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Pdf\ReceiptPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptPdf $pdf) {}

    public function download(Request $request, Receipt $receipt): Response
    {
        $receipt->load(['student.currentEnrollment.school', 'payment.invoice.term']);
        $content = $this->pdf->generate($receipt);
        $filename = "Risiti-{$receipt->receipt_number}.pdf";

        // ?download=1 forces a file save; the default stays inline so the browser's own
        // PDF viewer can open it (which is what the Print action relies on).
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
