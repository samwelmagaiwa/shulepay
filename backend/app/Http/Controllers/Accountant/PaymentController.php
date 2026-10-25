<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private PaymentProcessor $processor) {}

    /** The filters of the list, applied to the page and to its totals. */
    private function filtered(Request $request, $query, bool $withDates = true)
    {
        return $query
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('paid_at', $request->date))
            ->when($withDates && $request->filled('date_from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->date_from))
            ->when($withDates && $request->filled('date_to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference_number', 'like', $term)->orWhere('notes', 'like', $term)
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $term)
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('middle_name', 'like', $term))));
            });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // BelongsToSchool scope filters by active_school automatically
        $query = $this->filtered($request, Payment::with(['receipt', 'invoice.student', 'invoice.term'])->latest('paid_at')->latest('id'));

        $byMethod = $this->filtered($request, Payment::query())->selectRaw('method, COUNT(*) AS n, SUM(amount_cents) AS cents')->groupBy('method')->get();
        $day = fn ($from, $to) => (int) $this->filtered($request, Payment::query(), false)->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)->sum('amount_cents');

        return PaymentResource::collection($query->paginate(min(max($request->integer('per_page', 20), 5), 200)))->additional(['summary' => [
            'count' => (int) $byMethod->sum('n'),
            'total_cents' => (int) $byMethod->sum('cents'),
            'today_cents' => $day(now()->toDateString(), now()->toDateString()),
            'month_cents' => $day(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()),
            'by_method' => $byMethod->map(fn ($r) => ['method' => (string) ($r->method instanceof \BackedEnum ? $r->method->value : $r->method), 'count' => (int) $r->n, 'cents' => (int) $r->cents])->values(),
        ]]);
    }

    /**
     * Correct a payment that was recorded wrongly.
     *
     * The receipt stores no amount of its own — it renders from the payment —
     * so an edited payment reprints correctly without touching the receipt row.
     * The invoice status is re-derived because the balance has moved.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        if (! auth()->user()?->isSuperAdmin()
            && auth()->user()?->getAllPermissions()->contains('name', 'payments.edit_restricted')) {
            abort(403, __('messages.editing_payments_is_restricted_for_your_role'));
        }

        $data = $request->validate([
            'amount_cents' => ['sometimes', 'required', 'integer', 'min:1'],
            'method' => ['sometimes', 'required', 'string'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $before = $payment->toArray();

        DB::transaction(function () use ($payment, $data) {
            $payment->update($data);
            $payment->invoice?->syncStatus();
        });

        AuditLog::record('payment.updated', $payment, $before, $data);

        return response()->json(
            new PaymentResource($payment->fresh(['receipt', 'invoice.student']))
        );
    }

    /**
     * Reverse a payment.
     *
     * Soft-deleted rather than erased: a receipt for it is in someone's hands,
     * and the row is the only evidence of what was reversed and by whom.
     * paidCents() sums only live payments, so the invoice reopens on its own.
     *
     * Restricted to a superadmin — this reduces recorded collections, and an
     * accountant undoing their own receipted entry unseen is the wrong default.
     */
    public function destroy(Payment $payment): JsonResponse
    {
        if (! auth()->user()?->isSuperAdmin()) {
            return response()->json([
                'message' => __('messages.only_a_superadmin_can_reverse_a_payment'),
            ], 403);
        }

        $invoice = $payment->invoice;
        AuditLog::record('payment.reversed', $payment, $payment->toArray(), []);

        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();
            $invoice?->syncStatus();
        });

        return response()->json(['message' => __('messages.payment_reversed')]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $invoice = Invoice::with([
            'student.currentEnrollment.schoolClass',
            'student.currentEnrollment.school',
            'student.guardians',
            'term', 'school', 'academicYear',
        ])->findOrFail($request->invoice_id);
        $payment = $this->processor->record($invoice, $request->validated());
        $payment->load([
            'receipt',
            'invoice.payments',
            'invoice.student.currentEnrollment.schoolClass',
            'invoice.student.currentEnrollment.school',
            'invoice.student.guardians',
            'invoice.term',
            'invoice.school',
            'invoice.academicYear',
        ]);

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
