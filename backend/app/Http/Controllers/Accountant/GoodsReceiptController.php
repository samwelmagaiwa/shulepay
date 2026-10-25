<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\Suppliers\GoodsReceiptService;
use App\Services\Suppliers\SupplierAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(private GoodsReceiptService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with('supplier:id,name,code')->withCount('items')->orderByDesc('receipt_date')->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->trim(), '%_\\').'%';
            $query->where(fn ($q) => $q->where('grn_number', 'like', $term)->orWhere('delivery_note_no', 'like', $term)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)));
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'receipt_date' => 'required|date|before_or_equal:today',
            'delivery_note_no' => 'nullable|string|max:60',
            'supplier_bill_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.inventory_item_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01|max:9999999',
            'items.*.unit_cost_cents' => 'nullable|integer|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        if (! empty($data['supplier_bill_id'])) {
            SupplierBill::where('supplier_id', $supplier->id)->findOrFail($data['supplier_bill_id']);
        }

        return response()->json($this->service->receive($supplier, $data, $data['items'])->load('supplier:id,name,code'), 201);
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return response()->json($goodsReceipt->load(['supplier', 'items.inventoryItem:id,name,unit', 'bill:id,bill_number', 'recorder:id,name']));
    }

    public function void(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        SupplierAccess::authorize('grn_void');
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        return response()->json($this->service->void($goodsReceipt, $reason));
    }
}
