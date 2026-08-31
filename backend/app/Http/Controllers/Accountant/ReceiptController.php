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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
    /**
     * Every batch this large risked a slow surprise (or an outright 504,
     * since nginx's default 60s proxy read timeout has no override in this
     * app's nginx.conf) — measured live, 257 invoices took ~39s to render
     * even with BulkInvoicesPdf's memory-limit fix. Rather than just
     * rejecting anything over this, the frontend uses it to print in
     * sequential batches (see BATCH_SIZE below), so "print everything
     * matching the filter" still works for 400+ invoices — just as several
     * print jobs one after another instead of one that might time out.
     */
    private const MAX_BATCH = 150;

    /**
     * How many invoices the frontend requests per batch when auto-splitting
     * a large filtered set. Deliberately smaller than MAX_BATCH itself —
     * MAX_BATCH is the hard ceiling this endpoint enforces; BATCH_SIZE is
     * the size the frontend aims for so each batch renders quickly instead
     * of always maxing out the ceiling.
     */
    private const BATCH_SIZE = 50;

    private function scopedInvoiceQuery(Request $request): Builder
    {
        $query = Invoice::where('status', $request->status);

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

        return $query;
    }

    /**
     * A fast count-only check the frontend calls before printing (and again
     * whenever a filter changes) — so "how many will this print?" and "does
     * this need to be split into batches?" are answered instantly, without
     * paying for a full PDF render just to find out.
     */
    public function bulkByStatusCount(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:unpaid,partial,paid',
            'school_id' => 'nullable|integer|exists:schools,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'term_number' => 'nullable|integer|min:1|max:4',
        ]);

        $count = $this->scopedInvoiceQuery($request)->count();

        return response()->json([
            'count' => $count,
            'batch_size' => self::BATCH_SIZE,
            'batch_count' => (int) ceil($count / self::BATCH_SIZE),
            'max_batch' => self::MAX_BATCH,
        ]);
    }

    public function bulkByStatus(Request $request): Response
    {
        $request->validate([
            'status' => 'required|in:unpaid,partial,paid',
            'school_id' => 'nullable|integer|exists:schools,id',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'term_number' => 'nullable|integer|min:1|max:4',
            // The frontend paginates a large filtered set into sequential
            // batches of BATCH_SIZE each, printed one after another — these
            // two select which slice of the matching set this particular
            // request renders.
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_BATCH,
        ]);

        $query = $this->scopedInvoiceQuery($request)->with([
            'student.currentEnrollment.schoolClass', 'student.guardians', 'term', 'academicYear',
        ])->orderBy('school_id')->orderBy('student_id');

        $offset = $request->integer('offset', 0);
        $limit = $request->integer('limit', self::MAX_BATCH);
        $invoices = $query->skip($offset)->take($limit)->get();

        abort_if($invoices->isEmpty(), 404, 'Hakuna ankara zinazolingana na kigezo hiki.');

        abort_if(
            $invoices->count() > self::MAX_BATCH,
            422,
            'Ankara ni nyingi mno kuchapisha kwa mara moja (kikomo ni '.self::MAX_BATCH.') — punguza kigezo la kuchuja.'
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
