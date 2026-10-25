<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\StationaryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StationaryController extends Controller
{
    /**
     * List requests.
     * - Teaching staff: own requests only.
     * - Finance / admin staff: all requests for the school.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = StationaryRequest::with(['user:id,name', 'reviewer:id,name', 'item:id,name,quantity,unit'])
            ->orderByDesc('created_at');

        if (! $this->isTenantMode()) {
            $schoolId = $this->schoolIdForQuery($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }
        }

        if ($user->isStaff()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->get());
    }

    /**
     * Consumable items available in stock (for the request form dropdown).
     */
    public function availableItems(Request $request): JsonResponse
    {
        $query = InventoryItem::where('type', 'consumable');

        if (! $this->isTenantMode()) {
            $schoolId = $this->schoolIdForQuery($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }
        }

        $items = $query
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'unit']);

        return response()->json($items);
    }

    /**
     * Teacher submits a new request.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_requested' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $item = InventoryItem::findOrFail($data['item_id']);

        $reqData = [
            'user_id' => $user->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'unit' => $item->unit ?? 'pcs',
            'quantity_requested' => $data['quantity_requested'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ];

        if (! $this->isTenantMode()) {
            $reqData['school_id'] = $this->schoolIdForQuery($request);
        }

        $req = StationaryRequest::create($reqData);

        return response()->json($req->load('item:id,name,quantity,unit'), 201);
    }

    /**
     * Accountant approves a pending request (does not yet deduct stock).
     */
    public function approve(Request $request, StationaryRequest $stationaryRequest): JsonResponse
    {
        abort_if(! $stationaryRequest->isPending(), 422, __('messages.only_pending_requests_can_be_approved'));

        $stationaryRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json($stationaryRequest->fresh('reviewer'));
    }

    /**
     * Accountant provides the item — deducts from stock and records transaction.
     */
    public function provide(Request $request, StationaryRequest $stationaryRequest): JsonResponse
    {
        abort_if(! in_array($stationaryRequest->status, ['pending', 'approved'], true), 422, __('messages.already_provided_or_rejected'));

        $data = $request->validate([
            'quantity_provided' => ['required', 'numeric', 'min:0.5'],
        ]);

        $qty = (float) $data['quantity_provided'];

        DB::transaction(function () use ($stationaryRequest, $qty, $request) {
            $item = InventoryItem::lockForUpdate()->find($stationaryRequest->item_id);

            if ($item && (float) $item->quantity < $qty) {
                abort(422, __('Insufficient stock. Available: :quantity :unit.', ['quantity' => $item->quantity, 'unit' => $item->unit]));
            }

            // Deduct from stock
            if ($item) {
                $item->decrement('quantity', $qty);

                $txData = [
                    'item_id' => $item->id,
                    'type' => 'out',
                    'quantity' => $qty,
                    'notes' => "Issued to {$stationaryRequest->user->name} via stationary request #{$stationaryRequest->id}",
                    'issued_to' => $stationaryRequest->user->name,
                    'transaction_date' => now()->toDateString(),
                    'recorded_by' => $request->user()->id,
                ];
                if (! $this->isTenantMode()) {
                    $txData['school_id'] = $stationaryRequest->school_id;
                }
                InventoryTransaction::create($txData);
            }

            $stationaryRequest->update([
                'status' => 'provided',
                'quantity_provided' => $qty,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $stationaryRequest->reviewed_at ?? now(),
                'provided_at' => now(),
            ]);
        });

        return response()->json($stationaryRequest->fresh(['user:id,name', 'reviewer:id,name', 'item:id,name,quantity,unit']));
    }

    /**
     * Accountant rejects a pending request.
     */
    public function reject(Request $request, StationaryRequest $stationaryRequest): JsonResponse
    {
        abort_if(! $stationaryRequest->isPending(), 422, __('messages.only_pending_requests_can_be_rejected'));

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $stationaryRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return response()->json($stationaryRequest->fresh('reviewer'));
    }

    /**
     * Summary for owner / superadmin:
     * - Total requests by status
     * - Items issued in the current period
     * - Low-stock consumables
     */
    public function summary(Request $request): JsonResponse
    {
        $schoolId = $this->isTenantMode() ? null : $this->schoolIdForQuery($request);

        $counts = StationaryRequest::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentIssued = StationaryRequest::with('user:id,name')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('status', 'provided')
            ->orderByDesc('provided_at')
            ->limit(20)
            ->get(['id', 'user_id', 'item_name', 'quantity_provided', 'unit', 'provided_at']);

        $lowStock = InventoryItem::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('type', 'consumable')
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->get(['id', 'name', 'quantity', 'reorder_level', 'unit']);

        return response()->json([
            'counts' => $counts,
            'recent_issued' => $recentIssued,
            'low_stock' => $lowStock,
        ]);
    }
}
