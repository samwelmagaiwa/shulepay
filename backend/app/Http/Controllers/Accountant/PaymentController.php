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

    public function index(Request $request): AnonymousResourceCollection
    {
        // BelongsToSchool scope filters by active_school automatically
        $query = Payment::with(['receipt', 'invoice.student', 'invoice.term'])
            ->latest('paid_at');

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('date')) {
            $query->whereDate('paid_at', $request->date);
        }

        return PaymentResource::collection($query->paginate(20));
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
                'message' => 'Only a superadmin can reverse a payment.',
            ], 403);
        }

        $invoice = $payment->invoice;
        AuditLog::record('payment.reversed', $payment, $payment->toArray(), []);

        DB::transaction(function () use ($payment, $invoice) {
            $payment->delete();
            $invoice?->syncStatus();
        });

        return response()->json(['message' => 'Payment reversed.']);
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
