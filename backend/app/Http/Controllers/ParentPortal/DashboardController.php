<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Student;
use App\Services\Pdf\ReceiptPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/parent/dashboard
 *
 * Everything the parent portal landing page needs, in one scoped payload.
 *
 * The page previously issued one /parent/children call plus one /parent/statement
 * call per child, so a parent with four children made five round trips and the
 * cards populated one by one.
 *
 * Scoping rule: every query starts from $user->guardian->students(). A parent with
 * no guardian record sees an empty dashboard, never someone else's data.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guardian = $request->user()?->guardian;

        if (! $guardian) {
            return response()->json([
                'summary' => $this->emptySummary(),
                'children' => [],
                'recent_payments' => [],
            ]);
        }

        /** @var Collection<int, Student> $students */
        $students = $guardian->students()
            ->with([
                'currentEnrollment.schoolClass',
                'currentEnrollment.school',
                'currentEnrollment.academicYear',
            ])
            ->get();

        $studentIds = $students->pluck('id')->all();

        if (empty($studentIds)) {
            return response()->json([
                'summary' => $this->emptySummary(),
                'children' => [],
                'recent_payments' => [],
            ]);
        }

        // allSchools(): a guardian's children may attend different schools, and the
        // parent has no active-school context to scope by.
        $invoices = Invoice::allSchools()
            ->with(['term', 'academicYear', 'payments'])
            ->whereIn('student_id', $studentIds)
            ->get();

        $byStudent = $invoices->groupBy('student_id');

        $children = $students->map(function (Student $s) use ($byStudent) {
            $rows = $byStudent->get($s->id, collect());
            $enrollment = $s->currentEnrollment;

            $billed = $rows->sum(fn ($i) => $this->billedCents($i));
            $paid = $rows->sum(fn ($i) => $i->paidCents());

            return [
                'id' => $s->id,
                'name' => $s->fullName(),
                'photo' => $s->photo ? url('storage/'.$s->photo) : null,
                'status' => $s->status,
                'admission_number' => $enrollment?->admission_number,
                'school_class' => $enrollment?->schoolClass?->name,
                'school' => $enrollment?->school?->name,
                'academic_year' => $enrollment?->academicYear?->name,
                'billed_cents' => $billed,
                'paid_cents' => $paid,
                'balance_cents' => max(0, $billed - $paid),
                'invoice_count' => $rows->count(),

                // Per-term breakdown so a parent can see which term is outstanding
                // rather than only a single annual figure.
                'terms' => $rows
                    ->sortBy(fn ($i) => $i->term?->number ?? 0)
                    ->values()
                    ->map(function (Invoice $i) {
                        $b = $this->billedCents($i);
                        $p = $i->paidCents();

                        return [
                            'invoice_id' => $i->id,
                            'invoice_number' => $i->invoice_number,
                            'term' => $i->term?->name,
                            'term_number' => $i->term?->number,
                            'academic_year' => $i->academicYear?->name,
                            'billed_cents' => $b,
                            'paid_cents' => $p,
                            'balance_cents' => max(0, $b - $p),
                            'status' => $i->status?->value,
                        ];
                    })->all(),
            ];
        })->values();

        $totalBilled = $children->sum('billed_cents');
        $totalPaid = $children->sum('paid_cents');

        return response()->json([
            'summary' => [
                'children_count' => $children->count(),
                'total_billed_cents' => $totalBilled,
                'total_paid_cents' => $totalPaid,
                'total_balance_cents' => max(0, $totalBilled - $totalPaid),
                'cleared_count' => $children->where('balance_cents', 0)->count(),
                'pending_count' => $children->where('balance_cents', '>', 0)->count(),
            ],
            'children' => $children->all(),
            'recent_payments' => $this->recentPayments($studentIds, $students),
        ]);
    }

    /**
     * GET /api/parent/receipts/{receipt}
     *
     * The staff receipt route is role-gated, so a parent cannot use it. This serves
     * the same PDF but only for a receipt belonging to one of their own children.
     */
    public function receipt(Request $request, Receipt $receipt): Response
    {
        $guardian = $request->user()?->guardian;

        $owns = $guardian && $guardian->students()
            ->where('students.id', $receipt->student_id)
            ->exists();

        if (! $owns) {
            abort(403, 'Access denied — this receipt does not belong to your child.');
        }

        $content = app(ReceiptPdf::class)->generate($receipt);
        $filename = "Risiti-{$receipt->receipt_number}.pdf";
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    /** Invoice total as the parent owes it: fees + arrears - discount. */
    private function billedCents(Invoice $invoice): int
    {
        return $invoice->total_amount_cents->cents()
            + $invoice->arrears_cents->cents()
            - $invoice->discount_cents->cents();
    }

    /** Latest payments across this parent's children only. */
    private function recentPayments(array $studentIds, Collection $students): array
    {
        $names = $students->mapWithKeys(fn (Student $s) => [$s->id => $s->fullName()]);

        return Payment::withoutGlobalScopes()
            ->with(['receipt', 'invoice.term'])
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'student_id' => $p->student_id,
                'student_name' => $names[$p->student_id] ?? null,
                'amount_cents' => $p->amount_cents->cents(),
                'paid_at' => $p->paid_at?->toDateString(),
                'method' => $p->method?->value,
                'method_label' => $p->method?->label(),
                'term' => $p->invoice?->term?->name,
                'receipt_id' => $p->receipt?->id,
                'receipt_number' => $p->receipt?->receipt_number,
            ])->all();
    }

    private function emptySummary(): array
    {
        return [
            'children_count' => 0,
            'total_billed_cents' => 0,
            'total_paid_cents' => 0,
            'total_balance_cents' => 0,
            'cleared_count' => 0,
            'pending_count' => 0,
        ];
    }
}
