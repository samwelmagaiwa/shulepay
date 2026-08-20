<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Asset::with(['school', 'registeredBy'])->latest('created_at');

        $user = auth()->user();
        $requestedSchoolId = $request->filled('school_id')
            ? $request->integer('school_id')
            : ((int) $request->header('X-School-Id') ?: null);
        $schoolId = ($user->hasRole('superadmin') || $user->hasRole('owner') || $user->hasRole('accountant'))
            ? ($requestedSchoolId ?? $user->school_id)
            : $user->school_id;
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
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
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%");
            });
        }

        return AssetResource::collection($query->paginate(20));
    }

    public function nextTag(Request $request): JsonResponse
    {
        $request->validate(['school_id' => 'required|exists:schools,id']);
        $tag = Asset::nextAssetTag((int) $request->school_id);

        return response()->json(['asset_tag' => $tag]);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('assets', 'public');
        }

        $schoolId = auth()->user()->school_id ?? $data['school_id'];

        $asset = Asset::create([
            'school_id' => $schoolId,
            'asset_tag' => $data['asset_tag'],
            'name' => $data['name'],
            'category' => $data['category'],
            'quantity' => $data['quantity'] ?? 1,
            'serial_no' => $data['serial_no'] ?? null,
            'photo' => $photoPath,
            'purchase_cost_cents' => isset($data['purchase_cost']) ? (int) round($data['purchase_cost'] * 100) : 0,
            'purchase_date' => $data['purchase_date'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
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
            'registered_by' => auth()->id(),
            'registered_at' => now(),
        ]);

        AuditLogger::log('asset.created', $asset, ['after' => $asset->toArray()]);

        return response()->json(new AssetResource($asset->load(['school', 'registeredBy'])), 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        return response()->json(new AssetResource($asset->load(['school', 'registeredBy'])));
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $data = $request->validated();
        $before = $asset->toArray();

        $updateData = array_filter([
            'asset_tag' => $data['asset_tag'] ?? null,
            'name' => $data['name'] ?? null,
            'category' => $data['category'] ?? null,
            'school_id' => $data['school_id'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'serial_no' => $data['serial_no'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
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

        // Handle photo upload
        if ($request->hasFile('photo')) {
            if ($asset->photo) {
                Storage::disk('public')->delete($asset->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('assets', 'public');
        }

        $asset->update($updateData);

        AuditLogger::log('asset.updated', $asset, ['before' => $before, 'after' => $asset->toArray()]);

        return response()->json(new AssetResource($asset->load(['school', 'registeredBy'])));
    }

    public function dispose(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'disposal_date' => 'required|date',
            'disposal_value' => 'nullable|numeric|min:0',
            'disposal_reason' => 'required|string',
        ]);

        $before = $asset->toArray();

        $asset->update([
            'status' => 'disposed',
            'disposal_date' => $data['disposal_date'],
            'disposal_value_cents' => isset($data['disposal_value']) ? (int) round($data['disposal_value'] * 100) : 0,
            'disposal_reason' => $data['disposal_reason'],
        ]);

        AuditLogger::log('asset.disposed', $asset, ['before' => $before, 'after' => $asset->toArray()]);

        return response()->json(new AssetResource($asset->load(['school', 'registeredBy'])));
    }

    public function destroy(Asset $asset): JsonResponse
    {
        if (! in_array($asset->status, ['disposed', 'lost', 'written_off'])) {
            return response()->json([
                'message' => 'Mali inaweza kufutwa tu ikiwa ina hadhi: disposed, lost, au written_off.',
            ], 422);
        }

        AuditLogger::log('asset.deleted', $asset, ['before' => $asset->toArray()]);
        $asset->delete();

        return response()->json(['message' => 'Mali imefutwa.']);
    }
}
