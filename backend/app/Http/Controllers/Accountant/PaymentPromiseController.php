<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentPromise;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentPromiseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PaymentPromise::with(['student', 'invoice', 'guardian', 'recordedBy'])
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->school_id,  fn ($q) => $q->whereHas('student.currentEnrollment', fn ($e) => $e->where('school_id', $request->school_id)))
            ->orderByDesc('promised_date');

        return response()->json($q->paginate($request->per_page ?? 50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'invoice_id'    => 'required|exists:invoices,id',
            'guardian_id'   => 'nullable|exists:guardians,id',
            'promised_date' => 'required|date|after_or_equal:today',
            'amount_cents'  => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:1000',
        ]);

        // Validate invoice belongs to student and has a balance
        $invoice = Invoice::findOrFail($data['invoice_id']);
        abort_if($invoice->student_id !== (int) $data['student_id'], 422, 'Invoice does not belong to this student.');
        abort_if($invoice->balanceDueCents() <= 0, 422, 'This invoice has no outstanding balance.');

        $promise = PaymentPromise::create([
            ...$data,
            'recorded_by' => auth()->id(),
            'status'      => 'pending',
        ]);

        return response()->json($promise->load(['guardian', 'recordedBy', 'invoice']), 201);
    }

    public function update(Request $request, PaymentPromise $paymentPromise): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,kept,broken',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $paymentPromise->update($data);

        return response()->json($paymentPromise->fresh(['guardian', 'recordedBy', 'invoice']));
    }

    public function destroy(PaymentPromise $paymentPromise): JsonResponse
    {
        $paymentPromise->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
