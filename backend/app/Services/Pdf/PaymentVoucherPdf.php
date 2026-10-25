<?php

namespace App\Services\Pdf;

use App\Support\AmountInWords;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentVoucherPdf
{
    public function generate(array $voucher): string
    {
        $lh = SchoolLetterhead::for($voucher['school']);
        $words = AmountInWords::shillings($voucher['net_cents'], app()->getLocale());

        return Pdf::loadView('pdf.voucher', ['v' => $voucher, 'lh' => $lh, 'words' => $words])
            ->setPaper('a4')
            ->output();
    }
}
