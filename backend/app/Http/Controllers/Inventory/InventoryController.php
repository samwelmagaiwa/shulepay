<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Services\AuditLogger;
use App\Services\Inventory\LowStockAlertService;
use App\Services\Suppliers\SupplierSetup;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InventoryController extends Controller
{
    // â"€â"€ Shared helper â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    private function resolveSchoolId(Request $request): int
    {
        $user = auth()->user();
        $requested = $request->filled('school_id')
            ? $request->integer('school_id')
            : ((int) $request->header('X-School-Id') ?: null);

        $canSwitch = $user->hasRole('superadmin') || $user->hasRole('owner') || $user->hasRole('accountant');

        return ($canSwitch ? ($requested ?? $user->school_id) : $user->school_id) ?? $user->school_id;
    }

    // â"€â"€ Consumables â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->isTenantMode() ? null : $this->resolveSchoolId($request);
        $type = $request->input('type'); // 'fixed_asset' | 'consumable' | null

        if ($type === 'fixed_asset') {
            return $this->indexFixedAssets($request, $schoolId);
        }

        $query = InventoryItem::query()
            ->where('type', 'consumable')
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name');
        if (! $this->isTenantMode() && $schoolId) {
            $query->where('school_id', $schoolId);
        }
        $items = $query->get()
            ->map(function ($item) {
                $item->is_low_stock = $item->isLowStock();
                $item->total_value_cents = (int) round((float) $item->quantity * $item->unit_cost_cents->cents());

                return $item;
            });

        return response()->json($items);
    }

    private function indexFixedAssets(Request $request, ?int $schoolId): JsonResponse
    {
        $query = InventoryItem::query()->where('type', 'fixed_asset');
        if (! $this->isTenantMode() && $schoolId) {
            $query->where('school_id', $schoolId);
        }
        $query
            ->with('registeredBy:id,name')
            ->latest('created_at');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('asset_tag', 'like', "%{$s}%"));
        }

        $paginated = $query->paginate(20);

        $paginated->getCollection()->transform(function (InventoryItem $item) {
            $item->photo_url = $item->photo_url;
            $item->registered_by = $item->registeredBy?->name;
            $item->purchase_date = $item->purchase_date?->toDateString();
            $item->warranty_expiry = $item->warranty_expiry?->toDateString();
            $item->disposal_date = $item->disposal_date?->toDateString();
            $item->registered_at = $item->registered_at?->toDateTimeString();

            return $item;
        });

        return response()->json($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type', 'consumable');

        if ($type === 'fixed_asset') {
            return $this->storeFixedAsset($request);
        }

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

        $validated['type'] = 'consumable';
        $validated['school_id'] = $this->resolveSchoolId($request);
        $item = InventoryItem::create($validated);

        return response()->json($item, 201);
    }

    private function storeFixedAsset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_tag' => 'required|string|max:30|unique:inventory_items,asset_tag',
            'name' => 'required|string|max:200',
            'category' => 'required|in:furniture,technology,equipment,vehicles,buildings,sports,other',
            'school_id' => Tenant::active() ? 'sometimes|integer' : 'required|exists:schools,id',
            'quantity' => 'nullable|integer|min:1',
            'serial_no' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'supplier_name' => 'nullable|string|max:200',
            'invoice_no' => 'nullable|string|max:100',
            'funding_source' => 'nullable|in:fees,donation,grant',
            'depreciation_method' => 'nullable|in:straight_line,reducing_balance',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'depreciation_rate' => 'nullable|numeric|min:0.01|max:100',
            'salvage_value' => 'nullable|numeric|min:0',
            'custodian' => 'nullable|string|max:200',
            'location' => 'nullable|string|max:200',
            'condition' => 'nullable|in:excellent,good,fair,poor',
            'status' => 'nullable|in:in_use,under_repair,disposed,lost,written_off',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('assets', 'public');
        }

        if (Tenant::active()) {
            $data['school_id'] = Tenant::school()?->id;
        }
        $link = SupplierSetup::linkFrom($request);

        // Tenant inventory_items table carries no school_id column — every row
        // already belongs to the one school the tenant connection is scoped to.
        $item = InventoryItem::create(Tenant::stripSchoolId([
            'type' => 'fixed_asset',
            'school_id' => $data['school_id'],
            'asset_tag' => $data['asset_tag'],
            'name' => $data['name'],
            'category' => $data['category'],
            'quantity' => $data['quantity'] ?? 1,
            'serial_no' => $data['serial_no'] ?? null,
            'photo' => $photoPath,
            'purchase_cost_cents' => isset($data['purchase_cost']) ? (int) round($data['purchase_cost'] * 100) : 0,
            'purchase_date' => $data['purchase_date'] ?? null,
            'supplier_name' => $link['name'] ?? $data['supplier_name'] ?? null,
            'supplier_id' => $link['id'] ?? null,
            'invoice_no' => $data['invoice_no'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'depreciation_method' => $data['depreciation_method'] ?? null,
            'useful_life_years' => $data['useful_life_years'] ?? null,
            'depreciation_rate' => $data['depreciation_rate'] ?? null,
            'salvage_value_cents' => isset($data['salvage_value']) ? (int) round($data['salvage_value'] * 100) : 0,
            'custodian' => $data['custodian'] ?? null,
            'location' => $data['location'] ?? null,
            'condition' => $data['condition'] ?? 'good',
            'status' => $data['status'] ?? 'in_use',
            'warranty_expiry' => $data['warranty_expiry'] ?? null,
            'notes' => $data['notes'] ?? null,
            'registered_by' => Tenant::actorId(),
            'registered_at' => now(),
            'is_active' => true,
        ]));

        AuditLogger::log('inventory.asset_created', $item, ['after' => $item->toArray()]);

        $item->photo_url = $item->photo_url;
        $item->purchase_date = $item->purchase_date?->toDateString();
        $item->load('registeredBy:id,name');

        return response()->json($item, 201);
    }

    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        if ($item->type === 'fixed_asset') {
            return $this->updateFixedAsset($request, $item);
        }

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

    private function updateFixedAsset(Request $request, InventoryItem $item): JsonResponse
    {
        $before = $item->toArray();
        $id = $item->id;

        $data = $request->validate([
            'asset_tag' => "sometimes|required|string|max:30|unique:inventory_items,asset_tag,{$id}",
            'name' => 'sometimes|required|string|max:200',
            'category' => 'sometimes|required|in:furniture,technology,equipment,vehicles,buildings,sports,other',
            'school_id' => 'sometimes|required|exists:schools,id',
            'quantity' => 'nullable|integer|min:1',
            'serial_no' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'supplier_name' => 'nullable|string|max:200',
            'invoice_no' => 'nullable|string|max:100',
            'funding_source' => 'nullable|in:fees,donation,grant',
            'depreciation_method' => 'nullable|in:straight_line,reducing_balance',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'depreciation_rate' => 'nullable|numeric|min:0.01|max:100',
            'salvage_value' => 'nullable|numeric|min:0',
            'custodian' => 'nullable|string|max:200',
            'location' => 'nullable|string|max:200',
            'condition' => 'nullable|in:excellent,good,fair,poor',
            'status' => 'nullable|in:in_use,under_repair,disposed,lost,written_off',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $link = SupplierSetup::linkFrom($request);
        $updateData = array_filter([
            'asset_tag' => $data['asset_tag'] ?? null,
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'school_id' => $data['school_id'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'serial_no' => $data['serial_no'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'supplier_name' => $link['name'] ?? $data['supplier_name'] ?? null,
            'supplier_id' => $link['id'] ?? null,
            'invoice_no' => $data['invoice_no'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'depreciation_method' => $data['depreciation_method'] ?? null,
            'useful_life_years' => $data['useful_life_years'] ?? null,
            'depreciation_rate' => $data['depreciation_rate'] ?? null,
            'custodian' => $data['custodian'] ?? null,
            'location' => $data['location'] ?? null,
            'condition' => $data['condition'] ?? null,
            'status' => $data['status'] ?? null,
            'warranty_expiry' => $data['warranty_expiry'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($data['purchase_cost'])) {
            $updateData['purchase_cost_cents'] = (int) round($data['purchase_cost'] * 100);
        }
        if (isset($data['salvage_value'])) {
            $updateData['salvage_value_cents'] = (int) round($data['salvage_value'] * 100);
        }

        if ($request->hasFile('photo')) {
            if ($item->photo) {
                Storage::disk('public')->delete($item->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('assets', 'public');
        }

        $item->update($updateData);

        AuditLogger::log('inventory.asset_updated', $item, ['before' => $before, 'after' => $item->toArray()]);

        $item->photo_url = $item->photo_url;
        $item->purchase_date = $item->purchase_date?->toDateString();
        $item->warranty_expiry = $item->warranty_expiry?->toDateString();
        $item->load('registeredBy:id,name');

        return response()->json($item);
    }

    public function destroy(InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        if ($item->type === 'fixed_asset') {
            if (! in_array($item->status, ['disposed', 'lost', 'written_off'])) {
                return response()->json([
                    'message' => __('messages.an_asset_can_only_be_deleted_if'),
                ], 422);
            }
            AuditLogger::log('inventory.asset_deleted', $item, ['before' => $item->toArray()]);
        }

        $item->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    // â"€â"€ Dispose (fixed assets only) â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function dispose(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        if ($item->type !== 'fixed_asset') {
            return response()->json(['message' => __('messages.disposal_applies_to_fixed_assets_only')], 422);
        }

        $data = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_value' => 'nullable|numeric|min:0',
            'disposal_reason' => 'required|string',
        ]);

        $before = $item->toArray();

        $item->update([
            'status' => 'disposed',
            'disposal_date' => $data['disposal_date'],
            'disposal_value_cents' => isset($data['disposal_value']) ? (int) round($data['disposal_value'] * 100) : 0,
            'disposal_reason' => $data['disposal_reason'],
        ]);

        AuditLogger::log('inventory.asset_disposed', $item, ['before' => $before, 'after' => $item->toArray()]);

        return response()->json($item);
    }

    // â"€â"€ Next asset tag â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function nextTag(Request $request): JsonResponse
    {
        $request->validate(['school_id' => Tenant::active() ? 'sometimes|integer' : 'required|exists:schools,id']);
        $schoolId = Tenant::active() ? Tenant::school()?->id : $request->integer('school_id');
        $tag = InventoryItem::nextAssetTag((int) $schoolId);

        return response()->json(['asset_tag' => $tag]);
    }

    // â"€â"€ Consumable transactions â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function transaction(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        if ($item->type !== 'consumable') {
            return response()->json(['message' => __('messages.stock_transactions_are_for_consumable_items_only')], 422);
        }

        $user = auth()->user();
        if ($request->input('type') === 'adjustment' && ! $user->hasRole('superadmin') && ! $user->hasPermissionTo('inventory.adjustment')) {
            return response()->json(['message' => __('messages.you_do_not_have_permission_to_make')], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'issued_to' => 'nullable|string|max:200',
            'issued_to_employee_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string',
            'transaction_date' => 'required|date',
            'delivery_note_no' => 'nullable|string|max:60',
        ]);
        if ($link = SupplierSetup::linkFrom($request)) {
            $validated['supplier_id'] = $link['id'];
        }

        $validated['item_id'] = $item->id;
        $validated['school_id'] = $item->school_id;
        $validated['recorded_by'] = Tenant::actorId();

        $txn = InventoryTransaction::create(Tenant::stripSchoolId($validated));

        $qty = (float) $validated['quantity'];
        if ($validated['type'] === 'in') {
            $item->increment('quantity', $qty);
        } elseif ($validated['type'] === 'out') {
            $item->decrement('quantity', $qty);
        } else {
            $item->update(['quantity' => $qty]);
        }

        $item->refresh();
        $item->is_low_stock = $item->isLowStock();

        // Send low-stock SMS alert to accountant, owner, superadmin if below reorder level
        if (in_array($validated['type'], ['out', 'adjustment'])) {
            try {
                app(LowStockAlertService::class)->alertIfNeeded($item);
            } catch (\Throwable $e) {
                Log::warning('[LowStockAlert] '.$e->getMessage());
            }
        }

        return response()->json(['transaction' => $txn, 'item' => $item], 201);
    }

    public function transactions(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        $records = InventoryTransaction::where('item_id', $item->id)
            ->with(['recorder:id,name', 'issuedToEmployee:id,full_name,department,role'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($records);
    }

    // â"€â"€ Staff usage history for a consumable item â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function staffUsage(Request $request, InventoryItem $item): JsonResponse
    {
        $this->authorizeSchool($item->school_id);

        $records = InventoryTransaction::where('item_id', $item->id)
            ->where('type', 'out')
            ->whereNotNull('issued_to_employee_id')
            ->with(['issuedToEmployee:id,full_name,department,role,staff_number', 'recorder:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        // Group by employee
        $grouped = $records->groupBy('issued_to_employee_id')->map(function ($txns) {
            $emp = $txns->first()->issuedToEmployee;

            return [
                'employee' => $emp,
                'total_issued' => $txns->sum('quantity'),
                'transactions' => $txns->values(),
            ];
        })->values();

        return response()->json(['item' => $item->only(['id', 'name', 'unit']), 'staff_usage' => $grouped]);
    }

    // â"€â"€ Summary â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    public function summary(Request $request): JsonResponse
    {
        $schoolId = $this->isTenantMode() ? null : $this->resolveSchoolId($request);

        $consumableQuery = InventoryItem::query()->where('type', 'consumable')->where('is_active', true);
        $fixedQuery = InventoryItem::query()->where('type', 'fixed_asset');
        if (! $this->isTenantMode() && $schoolId) {
            $consumableQuery->where('school_id', $schoolId);
            $fixedQuery->where('school_id', $schoolId);
        }
        $consumables = $consumableQuery->get();
        $fixedAssets = $fixedQuery->get();

        $totalConsumableValue = $consumables->sum(
            fn ($i) => (int) round((float) $i->quantity * $i->unit_cost_cents->cents())
        );

        $lowStockCount = $consumables->filter(fn ($i) => $i->isLowStock())->count();
        $totalFixedAssetValue = $fixedAssets->sum(fn ($i) => (int) $i->current_value_cents);

        return response()->json([
            'total_items' => $consumables->count(),
            'low_stock_count' => $lowStockCount,
            'total_value_cents' => $totalConsumableValue,
            'total_fixed_assets' => $fixedAssets->count(),
            'fixed_assets_value_cents' => $totalFixedAssetValue,
            'fixed_assets_in_use' => $fixedAssets->where('status', 'in_use')->count(),
        ]);
    }

    // â"€â"€ Private helpers â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€â"€

    private function authorizeSchool(?int $schoolId): void
    {
        // A school's own database holds only that school's items (and its rows carry no school_id).
        if (Tenant::active()) {
            return;
        }

        $mySchool = app()->bound('active_school')
            ? app('active_school')->id
            : auth()->user()->school_id;

        if ($mySchool !== $schoolId) {
            abort(403, __('messages.unauthorized'));
        }
    }
}
