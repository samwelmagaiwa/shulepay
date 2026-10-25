<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\Suppliers\SupplierAccess;
use App\Services\Suppliers\SupplierLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierBillController extends Controller
{
    public function __construct(private SupplierLedger $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupplierBill::with('supplier:id,name,code')->orderByDesc('bill_date')->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('overdue')) {
            $query->whereIn('status', SupplierBill::OPEN)->whereDate('due_date', '<', now()->toDateString());
        }
        if ($request->boolean('open')) {
            $query->whereIn('status', SupplierBill::OPEN);
        }
        if ($request->filled('search')) {
            $query->where('bill_number', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('bill_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('bill_date', '<=', $request->to);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $supplier = Supplier::findOrFail($data['supplier_id']);
        $this->guardDuplicateNumber($supplier->id, $data['bill_number']);

        $bill = $this->ledger->createBill($supplier, $data, $data['items']);

        return response()->json($bill->load('supplier:id,name,code'), 201);
    }

    public function show(SupplierBill $bill): JsonResponse
    {
        return response()->json($bill->load(['supplier', 'items', 'allocations.payment', 'goodsReceipts', 'recorder:id,name', 'approver:id,name']));
    }

    public function update(Request $request, SupplierBill $bill): JsonResponse
    {
        $data = $this->validated($request, $bill);
        $this->guardDuplicateNumber($bill->supplier_id, $data['bill_number'], $bill->id);

        return response()->json($this->ledger->updateBill($bill, $data, $data['items']));
    }

    public function approve(SupplierBill $bill): JsonResponse
    {
        SupplierAccess::authorize('bill_approve');

        return response()->json($this->ledger->approveBill($bill));
    }

    public function reject(Request $request, SupplierBill $bill): JsonResponse
    {
        SupplierAccess::authorize('bill_approve');
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        return response()->json($this->ledger->rejectBill($bill, $reason));
    }

    public function cancel(Request $request, SupplierBill $bill): JsonResponse
    {
        SupplierAccess::authorize('bill_cancel');
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        return response()->json($this->ledger->cancelBill($bill, $reason));
    }

    public function attach(Request $request, SupplierBill $bill): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);

        if ($bill->attachment_path) {
            Storage::disk('public')->delete($bill->attachment_path);
        }
        $bill->update(['attachment_path' => $request->file('file')->store('supplier-bills', 'public')]);

        return response()->json(['attachment_path' => $bill->attachment_path, 'attachment_url' => Storage::disk('public')->url($bill->attachment_path)]);
    }

    private function validated(Request $request, ?SupplierBill $bill = null): array
    {
        return $request->validate([
            'supplier_id' => [$bill ? 'sometimes' : 'required', 'integer'],
            'bill_number' => 'required|string|max:60',
            'bill_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'vat_cents' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01|max:9999999',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.unit_price_cents' => 'required|integer|min:0',
            'items.*.inventory_item_id' => 'nullable|integer',
        ]);
    }

    private function guardDuplicateNumber(int $supplierId, string $number, ?int $exceptId = null): void
    {
        $exists = SupplierBill::withTrashed()->where('supplier_id', $supplierId)->where('bill_number', $number)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['bill_number' => __('suppliers.err.bill_number_taken')]);
        }
    }
}
