<?php
namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Pdf\ReceiptPdf;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller {
    public function __construct(private ReceiptPdf $pdf) {}

    public function download(Receipt $receipt): Response {
        $receipt->load(['student.currentEnrollment.school', 'payment.invoice.term']);
        $content  = $this->pdf->generate($receipt);
        $filename = "Risiti-{$receipt->receipt_number}.pdf";
        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
