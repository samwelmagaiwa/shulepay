<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupplierCategory;
use App\Services\Suppliers\SupplierAccess;
use App\Services\Suppliers\SupplierSettings;
use App\Services\Suppliers\SupplierSetup;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A school's supplier categories and purchasing rules (approval limit, tax rates). */
class SupplierSetupController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(SupplierSetup::categories()->map(fn ($c) => [
            'id' => $c->id, 'key' => $c->key, 'name' => $c->name, 'label' => $c->label(),
            'is_active' => (bool) $c->is_active, 'suppliers_count' => $c->suppliers()->count(),
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        SupplierSetup::categories();
        $category = SupplierCategory::create(Tenant::stripSchoolId([
            'school_id' => Tenant::school()?->id,
            'name' => $data['name'],
            'sort_order' => (int) SupplierCategory::max('sort_order') + 1,
        ]));

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, SupplierCategory $category): JsonResponse
    {
        $data = $request->validate(['name' => 'sometimes|nullable|string|max:100', 'is_active' => 'sometimes|boolean']);
        $category->update($data);

        return response()->json($category);
    }

    public function destroyCategory(SupplierCategory $category): JsonResponse
    {
        if ($category->suppliers()->exists()) {
            $category->update(['is_active' => false]);

            return response()->json(['message' => __('suppliers.category_hidden_in_use'), 'hidden' => true]);
        }
        $category->delete();

        return response()->json(['message' => __('suppliers.category_deleted'), 'hidden' => false]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(SupplierSettings::all());
    }

    /** What the signed-in user may do here; the screens hide or disable the rest. */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'is_owner' => SupplierAccess::isOwner(),
            'can' => SupplierAccess::map(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_approval_limit_cents' => 'sometimes|integer|min:0',
            'vat_rate' => 'sometimes|numeric|min:0|max:100',
            'wht_goods_rate' => 'sometimes|numeric|min:0|max:100',
            'wht_services_rate' => 'sometimes|numeric|min:0|max:100',
            'wht_threshold_cents' => 'sometimes|integer|min:0',
            'accountant_can' => 'sometimes|array',
            'accountant_can.*' => 'boolean',
        ]);
        $before = SupplierSettings::all();
        $after = SupplierSettings::save($data);
        AuditLog::record('supplier_settings.updated', null, $before, $after);

        return response()->json($after);
    }
}
