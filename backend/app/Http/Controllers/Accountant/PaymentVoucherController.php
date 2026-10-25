<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Services\Pdf\PaymentVoucherBuilder;
use App\Services\Pdf\PaymentVoucherPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The payment voucher of an approved supplier payment, an approved expense or a paid salary: a printable
 * A4 page in the school's letterhead with the payee, the figures, the amount in words and the signature lines.
 */
class PaymentVoucherController extends Controller
{
    public function __construct(private PaymentVoucherBuilder $builder, private PaymentVoucherPdf $pdf) {}

    public function download(Request $request, string $type, int $id): Response|JsonResponse
    {
        abort_unless(in_array($type, PaymentVoucherBuilder::TYPES, true), 404);

        try {
            $voucher = $this->builder->build($type, $id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $filename = $voucher['number'].'.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($this->pdf->generate($voucher), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
