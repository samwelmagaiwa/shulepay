<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
