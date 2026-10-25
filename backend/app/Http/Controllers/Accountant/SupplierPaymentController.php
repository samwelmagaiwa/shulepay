<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Suppliers\SupplierAccess;
use App\Services\Suppliers\SupplierLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierPaymentController extends Controller
{
    public function __construct(private SupplierLedger $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupplierPayment::with(['supplier:id,name,code', 'recorder:id,name', 'approver:id,name', 'allocations.bill:id,bill_number'])
            ->orderByDesc('payment_date')->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->trim(), '%_\\').'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $term)->orWhere('efd_number', 'like', $term)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)));
        }

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        // The old client sent 'paid_at'.
        if ($request->has('paid_at') && ! $request->has('payment_date')) {
            $request->merge(['payment_date' => $request->paid_at]);
        }

        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'amount_cents' => 'required|integer|min:1',
            'wht_cents' => 'nullable|integer|min:0',
            'method' => ['required', Rule::in(Supplier::METHODS)],
            'reference' => 'nullable|string|max:255',
            'efd_number' => 'nullable|string|max:60',
            'payment_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:2000',
            'allocations' => 'nullable|array|max:100',
            'allocations.*.supplier_bill_id' => 'required|integer',
            'allocations.*.amount_cents' => 'required|integer|min:1',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $payment = $this->ledger->createPayment($supplier, $data, $data['allocations'] ?? []);

        return response()->json($payment->load(['supplier:id,name,code', 'recorder:id,name', 'allocations']), 201);
    }

    public function approve(SupplierPayment $payment): JsonResponse
    {
        SupplierAccess::authorize('payment_approve');

        return response()->json($this->ledger->approvePayment($payment));
    }

    public function reject(Request $request, SupplierPayment $payment): JsonResponse
    {
        SupplierAccess::authorize('payment_approve');
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        return response()->json($this->ledger->rejectPayment($payment, $reason));
    }

    public function void(Request $request, SupplierPayment $payment): JsonResponse
    {
        SupplierAccess::authorize('payment_void');
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        return response()->json($this->ledger->voidPayment($payment, $reason));
    }
}
