<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReportController extends Controller
{
    /** What is owed, by how late it is: not yet due, 1-30, 31-60, 61-90 and 90+ days overdue. */
    public function aging(): JsonResponse
    {
        $today = now()->startOfDay();
        $buckets = ['current' => 0, 'd1_30' => 0, 'd31_60' => 0, 'd61_90' => 0, 'd90_plus' => 0];
        $rows = [];

        $bills = SupplierBill::with('supplier:id,name,code')->whereIn('status', SupplierBill::OPEN)->get();
        foreach ($bills as $b) {
            $due = $b->due_date ?? $b->bill_date;
            $late = (int) $due->diffInDays($today, false);
            $key = match (true) {
                $late <= 0 => 'current', $late <= 30 => 'd1_30', $late <= 60 => 'd31_60', $late <= 90 => 'd61_90', default => 'd90_plus',
            };
            $rows[$b->supplier_id] ??= ['supplier_id' => $b->supplier_id, 'supplier' => $b->supplier?->name, 'code' => $b->supplier?->code,
                'total_cents' => 0] + array_fill_keys(array_keys($buckets), 0);
            $rows[$b->supplier_id][$key] += $b->balanceCents();
            $rows[$b->supplier_id]['total_cents'] += $b->balanceCents();
            $buckets[$key] += $b->balanceCents();
        }

        return response()->json([
            'totals' => $buckets + ['total_cents' => array_sum($buckets)],
            'suppliers' => array_values($rows),
        ]);
    }

    /** What was bought: approved bills in a period, by supplier, by expense category and by month. */
    public function spend(Request $request): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $bills = SupplierBill::with(['supplier:id,name,code,supplier_category_id', 'supplier.category'])
            ->whereIn('status', SupplierBill::OWED)
            ->when($request->from, fn ($q, $d) => $q->whereDate('bill_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('bill_date', '<=', $d))
            ->get();

        $bySupplier = $bills->groupBy('supplier_id')->map(fn ($g) => [
            'supplier_id' => $g->first()->supplier_id, 'supplier' => $g->first()->supplier?->name,
            'bills' => $g->count(), 'total_cents' => (int) $g->sum('total_cents'),
        ])->sortByDesc('total_cents')->values();

        $byCategory = $bills->groupBy(fn ($b) => $b->supplier?->category?->label() ?: '')->map(fn ($g, $label) => [
            'category' => $label, 'bills' => $g->count(), 'total_cents' => (int) $g->sum('total_cents'),
        ])->sortByDesc('total_cents')->values();

        $byMonth = $bills->groupBy(fn ($b) => $b->bill_date->format('Y-m'))->map(fn ($g, $m) => [
            'month' => $m, 'total_cents' => (int) $g->sum('total_cents'),
        ])->sortKeys()->values();

        return response()->json([
            'total_cents' => (int) $bills->sum('total_cents'),
            'by_supplier' => $bySupplier, 'by_category' => $byCategory, 'by_month' => $byMonth,
        ]);
    }

    /** VAT paid to suppliers (input VAT) and tax withheld from them (WHT), for filing with TRA. */
    public function tax(Request $request): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $vat = SupplierBill::with('supplier:id,name,tin,vrn')->whereIn('status', SupplierBill::OWED)->where('vat_cents', '>', 0)
            ->when($request->from, fn ($q, $d) => $q->whereDate('bill_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('bill_date', '<=', $d))
            ->orderBy('bill_date')->get()->map(fn ($b) => [
                'date' => $b->bill_date->toDateString(), 'bill_number' => $b->bill_number, 'supplier' => $b->supplier?->name,
                'tin' => $b->supplier?->tin, 'vrn' => $b->supplier?->vrn,
                'subtotal_cents' => (int) $b->subtotal_cents, 'vat_cents' => (int) $b->vat_cents,
            ]);

        $wht = SupplierPayment::with('supplier:id,name,tin')->where('status', SupplierPayment::APPROVED)->where('wht_cents', '>', 0)
            ->when($request->from, fn ($q, $d) => $q->whereDate('payment_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('payment_date', '<=', $d))
            ->orderBy('payment_date')->get()->map(fn ($p) => [
                'date' => $p->payment_date->toDateString(), 'reference' => $p->reference, 'efd_number' => $p->efd_number,
                'supplier' => $p->supplier?->name, 'tin' => $p->supplier?->tin,
                'amount_cents' => (int) $p->amount_cents, 'wht_cents' => (int) $p->wht_cents,
            ]);

        return response()->json([
            'vat' => $vat, 'wht' => $wht,
            'total_vat_cents' => (int) $vat->sum('vat_cents'), 'total_wht_cents' => (int) $wht->sum('wht_cents'),
        ]);
    }

    /** One number per box for the dashboard strip on the module's first screen. */
    public function overview(): JsonResponse
    {
        $open = SupplierBill::whereIn('status', SupplierBill::OPEN);

        return response()->json([
            'total_owed_cents' => (int) Supplier::where('balance_cents', '>', 0)->sum('balance_cents'),
            'overdue_cents' => (int) (clone $open)->whereDate('due_date', '<', now()->toDateString())->get()->sum(fn ($b) => $b->balanceCents()),
            'due_this_week_cents' => (int) (clone $open)->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])->get()->sum(fn ($b) => $b->balanceCents()),
            'bills_awaiting_approval' => SupplierBill::where('status', SupplierBill::PENDING)->count(),
            'payments_awaiting_approval' => SupplierPayment::where('status', SupplierPayment::PENDING)->count(),
            'active_suppliers' => Supplier::where('status', Supplier::STATUS_ACTIVE)->count(),
        ]);
    }
}
