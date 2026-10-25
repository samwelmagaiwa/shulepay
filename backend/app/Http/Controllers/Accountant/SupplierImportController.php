<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Imports\SupplierSheet;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Services\Suppliers\SupplierNumbering;
use App\Services\Suppliers\SupplierSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Getting a school's existing suppliers into the module: a spreadsheet upload for the supplier list,
 * and a tool that points the free-text vendor names already typed on expenses, assets and stock
 * at real supplier records.
 */
class SupplierImportController extends Controller
{
    private const HEADERS = ['name', 'category', 'phone', 'email', 'tin', 'contact_name', 'address', 'region',
        'payment_terms_days', 'vat_registered', 'bank_name', 'bank_account_number', 'opening_balance_tzs'];

    public function template(): Response
    {
        $csv = implode(',', self::HEADERS)."\r\n"
            ."Kilimo Stationers,stationery,0712345678,sales@kilimo.co.tz,123-456-789,Asha Juma,Mwanza,Mwanza,30,yes,CRDB,0152345678900,0\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="suppliers-template.csv"',
        ]);
    }

    /** dry_run=1 only checks the sheet; without it the valid rows are saved. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120', 'dry_run' => 'nullable|boolean']);

        $sheet = Excel::toArray(new SupplierSheet, $request->file('file'))[0] ?? [];
        $categories = SupplierSetup::categories();
        $dry = $request->boolean('dry_run');

        $created = [];
        $problems = [];
        $seen = [];

        foreach ($sheet as $i => $raw) {
            $line = $i + 2; // row 1 is the heading
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $raw);
            if (! array_filter($row, fn ($v) => $v !== null && $v !== '')) {
                continue;
            }

            $v = Validator::make($row, [
                'name' => 'required|string|max:255',
                'phone' => ['nullable', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
                'email' => 'nullable|email|max:255',
                'tin' => ['nullable', 'regex:/^[0-9\-]{9,15}$/'],
                'payment_terms_days' => 'nullable|integer|min:0|max:365',
                'opening_balance_tzs' => 'nullable|numeric|min:0',
            ]);
            if ($v->fails()) {
                $problems[] = ['row' => $line, 'name' => $row['name'] ?? '', 'error' => $v->errors()->first()];

                continue;
            }

            $key = mb_strtolower($row['name']);
            $data = [
                'name' => $row['name'],
                'phone' => $row['phone'] ?? null,
                'tin' => $row['tin'] ?? null,
            ];
            if (isset($seen[$key]) || SupplierSetup::duplicatesOf($data)->isNotEmpty()) {
                $problems[] = ['row' => $line, 'name' => $row['name'], 'error' => __('suppliers.err.possible_duplicate'), 'duplicate' => true];

                continue;
            }
            $seen[$key] = true;

            $category = $this->category($categories, $row['category'] ?? null);
            $created[] = $row['name'];
            if ($dry) {
                continue;
            }

            $opening = (int) round(((float) ($row['opening_balance_tzs'] ?? 0)) * 100);
            $supplier = Supplier::create([
                'code' => SupplierNumbering::supplierCode(),
                'status' => Supplier::STATUS_ACTIVE,
                'name' => $row['name'],
                'supplier_category_id' => $category?->id,
                'contact_name' => $row['contact_name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'tin' => $row['tin'] ?? null,
                'address' => $row['address'] ?? null,
                'region' => $row['region'] ?? null,
                'payment_terms_days' => isset($row['payment_terms_days']) && $row['payment_terms_days'] !== '' ? (int) $row['payment_terms_days'] : null,
                'vat_registered' => in_array(mb_strtolower((string) ($row['vat_registered'] ?? '')), ['yes', 'y', '1', 'true', 'ndio'], true),
                'bank_name' => $row['bank_name'] ?? null,
                'bank_account_number' => $row['bank_account_number'] ?? null,
                'opening_balance_cents' => $opening,
                'balance_cents' => $opening,
            ]);
            AuditLog::record('supplier.imported', $supplier, [], ['name' => $supplier->name]);
        }

        return response()->json([
            'dry_run' => $dry,
            'to_create' => count($created),
            'created' => $dry ? 0 : count($created),
            'problems' => $problems,
        ]);
    }

    /** Vendor names typed on expenses, assets and stock that are not yet linked to a supplier. */
    public function vendors(): JsonResponse
    {
        $names = [];
        $add = function ($rows, string $source) use (&$names) {
            foreach ($rows as $name => $count) {
                $k = mb_strtolower(trim($name));
                $names[$k] ??= ['name' => trim($name), 'expenses' => 0, 'assets' => 0, 'items' => 0];
                $names[$k][$source] += (int) $count;
            }
        };
        $add(Expense::whereNull('supplier_id')->whereNotNull('vendor')->where('vendor', '!=', '')->selectRaw('vendor, COUNT(*) c')->groupBy('vendor')->pluck('c', 'vendor'), 'expenses');
        $add(Asset::whereNull('supplier_id')->whereNotNull('supplier_name')->where('supplier_name', '!=', '')->selectRaw('supplier_name, COUNT(*) c')->groupBy('supplier_name')->pluck('c', 'supplier_name'), 'assets');
        $add(InventoryItem::whereNull('supplier_id')->whereNotNull('supplier_name')->where('supplier_name', '!=', '')->selectRaw('supplier_name, COUNT(*) c')->groupBy('supplier_name')->pluck('c', 'supplier_name'), 'items');

        $byName = Supplier::get(['id', 'name'])->mapWithKeys(fn ($s) => [mb_strtolower($s->name) => $s->id]);
        $out = array_map(fn ($n) => $n + ['match_supplier_id' => $byName[mb_strtolower($n['name'])] ?? null], array_values($names));
        usort($out, fn ($a, $b) => ($b['expenses'] + $b['assets'] + $b['items']) <=> ($a['expenses'] + $a['assets'] + $a['items']));

        return response()->json($out);
    }

    /** Point every record carrying this vendor name at a supplier (an existing one, or a new one made from the name). */
    public function linkVendor(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'supplier_id' => 'nullable|integer']);

        $supplier = DB::transaction(function () use ($data) {
            $supplier = ! empty($data['supplier_id'])
                ? Supplier::findOrFail($data['supplier_id'])
                : Supplier::create([
                    'code' => SupplierNumbering::supplierCode(), 'status' => Supplier::STATUS_ACTIVE, 'name' => $data['name'],
                ]);

            $name = $data['name'];
            $linked = Expense::whereNull('supplier_id')->where('vendor', $name)->update(['supplier_id' => $supplier->id])
                + Asset::whereNull('supplier_id')->where('supplier_name', $name)->update(['supplier_id' => $supplier->id])
                + InventoryItem::whereNull('supplier_id')->where('supplier_name', $name)->update(['supplier_id' => $supplier->id]);
            AuditLog::record('supplier.vendor_linked', $supplier, [], ['name' => $name, 'records' => $linked]);
            $supplier->linked = $linked;

            return $supplier;
        });

        return response()->json(['supplier_id' => $supplier->id, 'linked' => $supplier->linked], 200);
    }

    private function category($categories, ?string $value)
    {
        if (! $value) {
            return null;
        }
        $v = mb_strtolower(trim($value));

        return $categories->first(fn ($c) => mb_strtolower((string) $c->key) === $v || mb_strtolower($c->label()) === $v
            || mb_strtolower(trans('suppliers.categories.'.$c->key, [], 'en')) === $v);
    }
}
