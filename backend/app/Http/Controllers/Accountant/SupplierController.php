<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierCategory;
use App\Models\SupplierDocument;
use App\Models\SupplierPayment;
use App\Services\Suppliers\SupplierAccess;
use App\Services\Suppliers\SupplierLedger;
use App\Services\Suppliers\SupplierNumbering;
use App\Services\Suppliers\SupplierSetup;
use App\Services\Suppliers\SupplierStatement;
use App\Services\Suppliers\SupplierTax;
use App\Support\SchoolLetterhead;
use App\Support\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Supplier::with(['school', 'category'])->orderBy('name');

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('code', 'like', $s)
                ->orWhere('phone', 'like', $s)->orWhere('tin', 'like', $s)->orWhere('contact_name', 'like', $s));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('supplier_category_id', $request->category_id);
        }
        if ($request->boolean('owing')) {
            $query->where('balance_cents', '>', 0);
        }
        if ($request->boolean('usable')) {
            $query->usable();
        }

        $page = $query->paginate($request->integer('per_page', 20));

        // How much of each supplier's open debt is already past its due date.
        $overdue = SupplierBill::whereIn('supplier_id', $page->pluck('id'))->whereIn('status', SupplierBill::OPEN)
            ->whereDate('due_date', '<', now()->toDateString())
            ->selectRaw('supplier_id, SUM(total_cents - paid_cents) as due')->groupBy('supplier_id')->pluck('due', 'supplier_id');
        $page->getCollection()->each(fn ($s) => $s->overdue_cents = (int) ($overdue[$s->id] ?? 0));

        return SupplierResource::collection($page);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request), $this->messages());
        $data = $this->normalise($this->zeroBlankNumbers($data));

        if (! $request->boolean('confirm_duplicate')) {
            $dupes = SupplierSetup::duplicatesOf($data);
            if ($dupes->isNotEmpty()) {
                return response()->json(['message' => __('suppliers.err.possible_duplicate'), 'duplicates' => $dupes], 409);
            }
        }

        $data['code'] = SupplierNumbering::supplierCode();
        $data['status'] = Supplier::STATUS_ACTIVE;
        $data['balance_cents'] = (int) ($data['opening_balance_cents'] ?? 0);

        $supplier = Supplier::create($data);
        AuditLog::record('supplier.created', $supplier, [], $data);

        return response()->json(new SupplierResource($supplier->load(['school', 'category'])), 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['school', 'category']);

        $open = SupplierBill::where('supplier_id', $supplier->id)->whereIn('status', SupplierBill::OPEN);

        return response()->json([
            ...(new SupplierResource($supplier))->resolve(),
            'summary' => [
                'open_bills' => (clone $open)->count(),
                'overdue_cents' => (int) (clone $open)->whereDate('due_date', '<', now()->toDateString())->sum(DB::raw('total_cents - paid_cents')),
                'pending_bills' => SupplierBill::where('supplier_id', $supplier->id)->where('status', SupplierBill::PENDING)->count(),
                'pending_payments' => SupplierPayment::where('supplier_id', $supplier->id)->where('status', SupplierPayment::PENDING)->count(),
                'total_billed_cents' => (int) SupplierBill::where('supplier_id', $supplier->id)->whereIn('status', SupplierBill::OWED)->sum('total_cents'),
                'total_paid_cents' => (int) SupplierPayment::where('supplier_id', $supplier->id)->where('status', SupplierPayment::APPROVED)->sum('amount_cents'),
                'goods_receipts' => GoodsReceipt::where('supplier_id', $supplier->id)->where('status', GoodsReceipt::RECEIVED)->count(),
                'expiring_documents' => SupplierDocument::where('supplier_id', $supplier->id)
                    ->whereDate('expiry_date', '<=', now()->addDays(SupplierDocument::EXPIRING_DAYS)->toDateString())->count(),
            ],
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate($this->rules($request, true), $this->messages());
        $data = $this->normalise($this->zeroBlankNumbers($data));
        // The opening balance is a starting figure; once the books move, only bills and payments change what is owed.
        unset($data['opening_balance_cents']);

        if (! $request->boolean('confirm_duplicate')) {
            $dupes = SupplierSetup::duplicatesOf($data + ['name' => $supplier->name], $supplier->id);
            if ($dupes->isNotEmpty() && (isset($data['name']) || isset($data['tin']) || isset($data['phone']))) {
                return response()->json(['message' => __('suppliers.err.possible_duplicate'), 'duplicates' => $dupes], 409);
            }
        }

        $before = $supplier->toArray();
        $supplier->update($data);
        AuditLog::record('supplier.updated', $supplier, $before, $data);

        return response()->json(new SupplierResource($supplier->load(['school', 'category'])));
    }

    /** A supplier with any history is deactivated, never deleted: the old bills and payments still refer to it. */
    public function destroy(Supplier $supplier): JsonResponse
    {
        SupplierAccess::authorize('supplier_deactivate');
        $hasHistory = $supplier->bills()->exists() || $supplier->payments()->exists() || $supplier->goodsReceipts()->exists()
            || (int) $supplier->balance_cents !== 0 || (int) $supplier->opening_balance_cents !== 0;

        if ($hasHistory) {
            $supplier->update(['status' => Supplier::STATUS_INACTIVE, 'deactivated_at' => now()]);
            AuditLog::record('supplier.deactivated', $supplier, [], ['status' => Supplier::STATUS_INACTIVE]);

            return response()->json(['message' => __('suppliers.deactivated_instead'), 'deactivated' => true]);
        }

        AuditLog::record('supplier.deleted', $supplier, $supplier->toArray(), []);
        $supplier->delete();

        return response()->json(['message' => __('messages.supplier_deleted'), 'deactivated' => false]);
    }

    public function setStatus(Request $request, Supplier $supplier): JsonResponse
    {
        SupplierAccess::authorize('supplier_deactivate');
        $status = $request->validate(['status' => ['required', Rule::in(Supplier::STATUSES)]])['status'];
        $supplier->update([
            'status' => $status,
            'deactivated_at' => $status === Supplier::STATUS_INACTIVE ? now() : null,
        ]);
        AuditLog::record('supplier.status', $supplier, [], ['status' => $status]);

        return response()->json(new SupplierResource($supplier->load(['school', 'category'])));
    }

    /** Fold a duplicate supplier into this one: everything it owns moves across, then it is removed. */
    public function merge(Request $request, Supplier $supplier): JsonResponse
    {
        SupplierAccess::authorize('supplier_merge');
        $request->validate(['from_id' => 'required|integer']);
        $from = Supplier::findOrFail($request->from_id);
        abort_if($from->id === $supplier->id, 422, __('suppliers.err.merge_same'));

        DB::transaction(function () use ($supplier, $from) {
            foreach (['supplier_bills', 'supplier_payments', 'supplier_documents', 'goods_receipts', 'expenses', 'assets', 'inventory_items', 'inventory_transactions'] as $table) {
                DB::table($table)->where('supplier_id', $from->id)->update(['supplier_id' => $supplier->id]);
            }
            $supplier->update([
                'opening_balance_cents' => (int) $supplier->opening_balance_cents + (int) $from->opening_balance_cents,
            ]);
            AuditLog::record('supplier.merged', $supplier, [], ['from' => $from->id, 'from_name' => $from->name]);
            $from->forceDelete();
            app(SupplierLedger::class)->recompute($supplier);
        });

        return response()->json(new SupplierResource($supplier->fresh(['school', 'category'])));
    }

    public function statement(Request $request, Supplier $supplier): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        return response()->json([
            'supplier' => (new SupplierResource($supplier->load(['school', 'category'])))->resolve(),
            ...SupplierStatement::build($supplier, $request->from, $request->to),
        ]);
    }

    public function statementPdf(Request $request, Supplier $supplier)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $pdf = Pdf::loadView('pdf.supplier_statement', [
            'lh' => SchoolLetterhead::for(Tenant::school()),
            'supplier' => $supplier,
            'statement' => SupplierStatement::build($supplier, $request->from, $request->to),
            'from' => $request->from,
            'to' => $request->to,
        ])->setPaper('a4');

        return $pdf->stream('supplier-statement-'.($supplier->code ?: $supplier->id).'.pdf');
    }

    /** The WHT the school would withhold on paying this supplier this amount. */
    public function whtSuggestion(Request $request, Supplier $supplier): JsonResponse
    {
        $amount = (int) $request->validate(['amount_cents' => 'required|integer|min:1'])['amount_cents'];

        return response()->json(['wht_cents' => SupplierTax::suggestWht($supplier, $amount)]);
    }

    /** A blank terms/limit/opening-balance box arrives as null; those columns hold 0 when there is nothing to say. */
    private function zeroBlankNumbers(array $data): array
    {
        foreach (['payment_terms_days', 'credit_limit_cents', 'opening_balance_cents'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                $data[$key] = 0;
            }
        }

        return $data;
    }

    /** TIN as 123-456-789 and VRN as 40-012345-X, whichever way they were typed. */
    private function normalise(array $data): array
    {
        if (! empty($data['tin'])) {
            $d = preg_replace('/\D/', '', $data['tin']);
            $data['tin'] = substr($d, 0, 3).'-'.substr($d, 3, 3).'-'.substr($d, 6, 3);
        }
        if (! empty($data['vrn'])) {
            $v = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $data['vrn']));
            $data['vrn'] = substr($v, 0, 2).'-'.substr($v, 2, 6).'-'.substr($v, 8, 1);
        }

        return $data;
    }

    /**
     * What a supplier record must contain. Everything beyond name, category and phone is optional,
     * but groups hang together: a VAT-registered supplier needs its TIN and VRN, a bank account needs
     * the bank and the number, mobile money needs the network and the number, and a preferred
     * payment method needs the details to pay by it.
     */
    private function rules(Request $r, bool $update = false): array
    {
        $phone = ['regex:/^\+?[0-9\s\-]{9,20}$/'];
        $taxed = fn () => $r->boolean('vat_registered') || $r->boolean('wht_applicable');
        $pref = $r->input('preferred_method');
        $payByBank = $pref === 'bank';
        $payByMobile = in_array($pref, Supplier::MOBILE_PROVIDERS, true) && $pref !== 'other';

        return [
            'name' => [...($update ? ['sometimes'] : []), 'required', 'string', 'min:2', 'max:255'],
            'supplier_category_id' => [$update ? 'nullable' : 'required', 'integer', fn ($a, $v, $fail) => $v && ! SupplierCategory::whereKey($v)->exists() ? $fail(__('validation.exists', ['attribute' => $a])) : null],
            'contact_name' => ['nullable', 'string', 'max:255', 'regex:/\p{L}/u'],
            'contact_title' => 'nullable|string|max:100',
            'phone' => [$update ? 'nullable' : 'required', 'string', 'max:20', ...$phone],
            'alt_phone' => ['nullable', 'string', 'max:20', ...$phone],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'tin' => [Rule::requiredIf($taxed), 'nullable', 'string', 'max:20', 'regex:/^\d{3}-?\d{3}-?\d{3}$/'],
            'vrn' => [Rule::requiredIf(fn () => $r->boolean('vat_registered')), 'nullable', 'string', 'max:30', 'regex:/^\d{2}-?\d{6}-?[A-Za-z]$/'],
            'business_reg_no' => 'nullable|string|max:50',
            'vat_registered' => 'boolean',
            'wht_applicable' => 'boolean',
            'wht_type' => [Rule::requiredIf(fn () => $r->boolean('wht_applicable')), 'nullable', Rule::in(SupplierTax::WHT_TYPES)],
            'bank_name' => [Rule::requiredIf(fn () => filled($r->input('bank_account_number')) || $payByBank), 'nullable', 'string', 'max:100'],
            'bank_account_name' => 'nullable|string|max:150',
            'bank_account_number' => [Rule::requiredIf(fn () => filled($r->input('bank_name')) || $payByBank), 'nullable', 'string', 'max:40', 'regex:/^[0-9\s\-]{6,40}$/'],
            'mobile_money_provider' => [Rule::requiredIf(fn () => filled($r->input('mobile_money_number')) || $payByMobile), 'nullable', Rule::in(Supplier::MOBILE_PROVIDERS)],
            'mobile_money_number' => [Rule::requiredIf(fn () => filled($r->input('mobile_money_provider')) || $payByMobile), 'nullable', 'string', 'max:20', ...$phone],
            'mobile_money_name' => 'nullable|string|max:150',
            'preferred_method' => ['nullable', Rule::in(Supplier::METHODS)],
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    private function messages(): array
    {
        return collect([
            'name.required', 'name.min', 'supplier_category_id.required', 'contact_name.regex', 'phone.required', 'phone.regex',
            'alt_phone.regex', 'email.email', 'tin.required', 'tin.regex', 'vrn.required', 'vrn.regex', 'wht_type.required',
            'bank_name.required', 'bank_account_number.required', 'bank_account_number.regex',
            'mobile_money_provider.required', 'mobile_money_number.required', 'mobile_money_number.regex',
            'payment_terms_days.integer', 'payment_terms_days.min', 'payment_terms_days.max',
        ])->mapWithKeys(fn ($k) => [$k => __('suppliers.validation.'.$k)])->all();
    }
}
