<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use App\Services\Pdf\BulkInvoicesPdf;
use App\Services\Pdf\ReceiptPdf;
use App\Services\Pdf\StudentStatementPdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private ReceiptPdf $pdf,
        private StudentStatementPdf $statementPdf,
        private BulkInvoicesPdf $bulkPdf,
    ) {}

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

    /**
     * One consolidated receipt covering every invoice a student has — the
     * Invoices page's "Print Receipt" action on a student's collapsed row,
     * so a single printout shows all terms' debts, payments, and balance
     * instead of one receipt per invoice.
     */
    public function downloadStatement(Request $request, Student $student): Response
    {
        $user = $request->user();
        $schoolId = Invoice::allSchools()->where('student_id', $student->id)->value('school_id');
        if ($schoolId && ! $user->isSuperAdmin() && ! $user->canAccessSchool($schoolId)) {
            abort(403, 'Access denied.');
        }

        $content = $this->statementPdf->generate($student);
        $filename = 'Taarifa-'.str_replace(' ', '-', $student->fullName()).'.pdf';

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Every invoice matching a status filter (Partial / Unpaid) printed as
     * one document — the Invoices page's bulk-print action, reusing the same
     * school/class/term filters the list itself is filtered by. Invoice's
     * own BelongsToSchool scope already restricts this to the active school
     * (or every accessible school in "Shule Zote" mode), same as the list —
     * no separate ownership check needed here, unlike the single-record
     * lookups above.
     */
    public function bulkByStatus(Request $request): Response
    {
        $request->validate([
            'status' => 'required|in:unpaid,partial,paid',
            'school_id' => 'nullable|integer|exists:schools,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'term_number' => 'nullable|integer|min:1|max:4',
        ]);

        $query = Invoice::with([
            'student.currentEnrollment.schoolClass', 'student.guardians', 'term', 'academicYear',
        ])->where('status', $request->status);

        if ($request->filled('school_id') && (int) $request->school_id !== 0) {
            $query->where('school_id', $request->school_id);
        }
        if ($request->filled('school_class_id')) {
            $query->whereHas(
                'student.currentEnrollment',
                fn ($q) => $q->where('school_class_id', $request->school_class_id)
            );
        }
        if ($request->filled('term_number')) {
            $query->whereHas('term', fn ($q) => $q->where('number', (int) $request->term_number));
        }

        $invoices = $query->orderBy('school_id')->orderBy('student_id')->get();

        abort_if($invoices->isEmpty(), 404, 'Hakuna ankara zinazolingana na kigezo hiki.');

        // Measured live: 257 invoices took ~39s to render even with the
        // memory-limit fix in BulkInvoicesPdf — nginx's default 60s proxy
        // read timeout has no override in this app's nginx.conf, so a batch
        // near 300 risks a 504 rather than a clean response. 150 keeps the
        // worst case comfortably under that with room for slower days.
        abort_if(
            $invoices->count() > 150,
            422,
            'Ankara ni nyingi mno kuchapisha kwa mara moja (kikomo ni 150) — punguza kigezo la kuchuja.'
        );

        $content = $this->bulkPdf->generate($invoices, $request->status);
        $filename = 'Ankara-'.$request->status.'-'.now()->format('Ymd').'.pdf';

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
