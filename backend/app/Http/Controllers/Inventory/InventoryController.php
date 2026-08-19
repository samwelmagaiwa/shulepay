<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id;

        $items = InventoryItem::where('school_id', $schoolId)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $item->is_low_stock = $item->isLowStock();
                $item->total_value_cents = (int) round((float) $item->quantity * $item->unit_cost_cents->cents());

                return $item;
            });

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:expense_categories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'quantity' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost_cents' => 'nullable|integer|min:0',
            'location' => 'nullable|string',
        ]);

        $validated['school_id'] = app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id;
        $item = InventoryItem::create($validated);

        return response()->json($item, 201);
    }

    public function update(Request $request, InventoryItem $item)
    {
        $this->authorizeSchool($item->school_id);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:expense_categories,id',
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'unit' => 'sometimes|string',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost_cents' => 'nullable|integer|min:0',
            'location' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy(InventoryItem $item)
    {
        $this->authorizeSchool($item->school_id);
        $item->delete();

        return response()->noContent();
    }

    public function transaction(Request $request, InventoryItem $item)
    {
        $this->authorizeSchool($item->school_id);

        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        $validated['item_id'] = $item->id;
        $validated['school_id'] = $item->school_id;
        $validated['recorded_by'] = auth()->id();

        $txn = InventoryTransaction::create($validated);

        // Update item quantity
        $qty = (float) $validated['quantity'];
        if ($validated['type'] === 'in') {
            $item->increment('quantity', $qty);
        } elseif ($validated['type'] === 'out') {
            $item->decrement('quantity', $qty);
        } else {
            // adjustment: set absolute quantity
            $item->update(['quantity' => $qty]);
        }

        $item->refresh();
        $item->is_low_stock = $item->isLowStock();

        return response()->json(['transaction' => $txn, 'item' => $item], 201);
    }

    public function transactions(Request $request, InventoryItem $item)
    {
        $this->authorizeSchool($item->school_id);

        $records = InventoryTransaction::where('item_id', $item->id)
            ->with('recorder:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($records);
    }

    public function summary(Request $request)
    {
        $schoolId = app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id;

        $items = InventoryItem::where('school_id', $schoolId)->where('is_active', true)->get();

        $totalValueCents = $items->sum(fn ($i) => (int) round((float) $i->quantity * $i->unit_cost_cents->cents()));
        $lowStockCount = $items->filter(fn ($i) => $i->isLowStock())->count();

        return response()->json([
            'total_items' => $items->count(),
            'low_stock_count' => $lowStockCount,
            'total_value_cents' => $totalValueCents,
        ]);
    }

    private function authorizeSchool(int $schoolId): void
    {
        if (app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id !== $schoolId) {
            abort(403, 'Unauthorized');
        }
    }
}
