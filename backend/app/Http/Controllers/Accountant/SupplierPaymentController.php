<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::with(['supplier', 'recorder'])->latest('payment_date');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'amount_cents' => 'required|integer|min:1',
            'method' => 'required|in:cash,bank,mpesa,cheque',
            'reference' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $supplier = Supplier::lockForUpdate()->findOrFail($data['supplier_id']);

            $data['recorded_by'] = auth()->id();

            $payment = SupplierPayment::create($data);

            // Reduce what we owe the supplier
            $current = (int) $supplier->getRawOriginal('balance_cents');
            $supplier->update(['balance_cents' => $current - $data['amount_cents']]);

            AuditLog::record('supplier_payment.created', $payment, [], $data);

            return response()->json($payment->load(['supplier', 'recorder']), 201);
        });
    }

    public function destroy(SupplierPayment $supplierPayment): JsonResponse
    {
        DB::transaction(function () use ($supplierPayment) {
            // Reverse the balance reduction
            $supplier = Supplier::lockForUpdate()->findOrFail($supplierPayment->supplier_id);
            $current = (int) $supplier->getRawOriginal('balance_cents');
            $supplier->update(['balance_cents' => $current + (int) $supplierPayment->getRawOriginal('amount_cents')]);

            AuditLog::record('supplier_payment.deleted', $supplierPayment, $supplierPayment->toArray(), []);
            $supplierPayment->delete();
        });

        return response()->json(['message' => 'Payment deleted and balance reversed.']);
    }
}
