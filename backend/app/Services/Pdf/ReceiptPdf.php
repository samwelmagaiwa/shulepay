<?php
namespace App\Services\Pdf;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdf {
    public function generate(Receipt $receipt): string {
        return Pdf::loadView('pdf.receipt', compact('receipt'))
            ->setPaper([0, 0, 226.77, 453.54]) // 80mm × 160mm thermal
            ->output();
    }
}
