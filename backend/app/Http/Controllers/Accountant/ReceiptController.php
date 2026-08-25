<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\Pdf\ReceiptPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptPdf $pdf) {}

    public function download(Request $request, Receipt $receipt): Response
    {
        // Receipt has no school_id column of its own (see receipts migration), so
        // ownership is checked via the school on its payment — queried with the
        // school scope explicitly bypassed, since the currently-active school
        // context (bound from an optional X-School-Id header) may not match a
        // legitimate multi-school accountant's *other* school, which would
        // otherwise make this come back null and wrongly deny a real user.
        // Without this check at all, any finance-staff user — regardless of which
        // school they belong to — could download another school's receipts by
        // guessing the sequential {receipt} id in the URL.
        $user = $request->user();
        $schoolId = Payment::allSchools()->where('receipt_id', $receipt->id)->value('school_id');
        if (! $schoolId || (! $user->isSuperAdmin() && ! $user->canAccessSchool($schoolId))) {
            abort(403, 'Access denied.');
        }

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
