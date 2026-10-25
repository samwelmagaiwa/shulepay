<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SupplierSetup
{
    /** The school's categories, seeding the standard list the first time it is asked for. */
    public static function categories(): Collection
    {
        if (! SupplierCategory::query()->exists()) {
            foreach (SupplierCategory::DEFAULTS as $i => $key) {
                SupplierCategory::create(Tenant::stripSchoolId([
                    'school_id' => Tenant::school()?->id,
                    'key' => $key,
                    'sort_order' => $i,
                ]));
            }
        }

        return SupplierCategory::orderBy('sort_order')->orderBy('id')->get();
    }

    /** Other live suppliers of this school that look like the same business (same TIN, phone or name). */
    public static function duplicatesOf(array $data, ?int $exceptId = null): Collection
    {
        $query = Supplier::query()->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($data) {
                if (! empty($data['tin'])) {
                    $q->orWhere('tin', $data['tin']);
                }
                if (! empty($data['phone'])) {
                    $q->orWhere('phone', $data['phone']);
                }
                if (! empty($data['name'])) {
                    $q->orWhereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['name']))]);
                }
            });

        return $query->get(['id', 'code', 'name', 'phone', 'tin']);
    }

    /**
     * The supplier a form picked (`supplier_id`), found within this school only, or [] when none was picked.
     * Lets expenses, assets and stock-ins point at the supplier record instead of free text.
     *
     * @return array{id?: int, name?: string}
     */
    public static function linkFrom(Request $request): array
    {
        if (! $request->filled('supplier_id')) {
            return [];
        }
        $supplier = Supplier::findOrFail((int) $request->input('supplier_id'));

        return ['id' => $supplier->id, 'name' => $supplier->name];
    }
}
