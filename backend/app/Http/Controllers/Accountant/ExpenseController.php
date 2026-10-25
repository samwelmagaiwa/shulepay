<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Refund;
use App\Services\Budget\BudgetActuals;
use App\Services\Suppliers\SupplierSetup;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    /** The filters of the list, applied to any query (the list itself and its totals). */
    private function filtered(Request $request, $query, bool $withDates = true)
    {
        return $query
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('payment_mode'), fn ($q) => $q->where('payment_mode', $request->payment_mode))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($withDates && $request->filled('date_from'), fn ($q) => $q->where('expense_date', '>=', $request->date_from))
            ->when($withDates && $request->filled('date_to'), fn ($q) => $q->where('expense_date', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)->orWhere('vendor', 'like', $term)
                    ->orWhere('receipt_reference', 'like', $term)->orWhere('notes', 'like', $term));
            });
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => 'nullable|integer|min:5|max:500']);

        $query = $this->filtered($request, Expense::with(['school', 'category', 'recorder', 'approver', 'supplier:id,name,code', 'supplierBill:id,bill_number,status'])
            ->latest('expense_date')->latest('id'));

        // Totals of everything the filters match (not just the page on screen): what was spent, what waits, what is owed.
        $rows = $this->filtered($request, Expense::query())
            ->selectRaw('status, payment_mode, COUNT(*) AS n, SUM(amount_cents) AS cents')->groupBy('status', 'payment_mode')->get();
        $sum = fn ($set, string $key = 'cents') => (int) $set->sum($key);
        $approved = $rows->where('status', 'approved');
        $topCategories = $this->filtered($request, Expense::query())->where('status', 'approved')
            ->selectRaw('category_id, SUM(amount_cents) AS cents')->groupBy('category_id')->orderByDesc('cents')->limit(5)->get();
        $names = ExpenseCategory::whereIn('id', $topCategories->pluck('category_id'))->pluck('name', 'id');

        return ExpenseResource::collection($query->paginate($request->integer('per_page', 20)))->additional(['summary' => [
            'approved_cents' => $sum($approved),
            'paid_cents' => $sum($approved->where('payment_mode', '!=', 'credit')),
            'credit_cents' => $sum($approved->where('payment_mode', 'credit')),
            'pending_cents' => $sum($rows->where('status', 'pending')),
            'pending_count' => $sum($rows->where('status', 'pending'), 'n'),
            'rejected_count' => $sum($rows->where('status', 'rejected'), 'n'),
            'count' => $sum($rows, 'n'),
            'this_month_cents' => (int) $this->filtered($request, Expense::query(), false)->where('status', 'approved')
                ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount_cents'),
            'refunds_cents' => (int) Refund::query()
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('refunded_at', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('refunded_at', '<=', $request->date_to))
                ->sum('amount_cents'),
            'top_categories' => $topCategories->map(fn ($r) => ['name' => $names[$r->category_id] ?? '—', 'cents' => (int) $r->cents])->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'amount_cents' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'vendor' => 'nullable|string|max:255',
            'receipt_reference' => 'nullable|string|max:255',
            'expense_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $data['recorded_by'] = Tenant::actorId();
        $data['status'] = 'pending';
        if ($link = SupplierSetup::linkFrom($request)) {
            $data['supplier_id'] = $link['id'];
            $data['vendor'] = $link['name'];
        }

        $expense = Expense::create($data);
        AuditLog::record('expense.created', $expense, [], $data);

        return response()->json((new ExpenseResource($expense->load(['school', 'category', 'recorder'])))->resolve() + ['budget_warning' => app(BudgetActuals::class)->warningFor($expense)], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return response()->json(['message' => __('messages.only_pending_expenses_can_be_updated')], 422);
        }

        $data = $request->validate([
            'category_id' => 'sometimes|required|exists:expense_categories,id',
            'amount_cents' => 'sometimes|required|integer|min:1',
            'description' => 'sometimes|required|string|max:500',
            'vendor' => 'nullable|string|max:255',
            'receipt_reference' => 'nullable|string|max:255',
            'expense_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
        ]);

        if ($link = SupplierSetup::linkFrom($request)) {
            $data['supplier_id'] = $link['id'];
            $data['vendor'] = $link['name'];
        }

        $before = $expense->toArray();
        $expense->update($data);
        AuditLog::record('expense.updated', $expense, $before, $data);

        return response()->json((new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])))->resolve() + ['budget_warning' => app(BudgetActuals::class)->warningFor($expense)]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        // Removing spending from the books is the owner's decision: the accountant records, the owner removes.
        if (! auth()->user()?->hasAnyRole(['owner', 'superadmin'])) {
            return response()->json(['message' => __('messages.expense_delete_admin_only')], 403);
        }

        // An expense booked from a supplier bill goes away only by cancelling that bill.
        if ($expense->supplier_bill_id) {
            return response()->json(['message' => __('messages.expense_from_supplier_bill')], 422);
        }

        // Approved spending is a financial record, so it is normally immutable —
        // an accountant cannot remove one after it has been signed off. A
        // superadmin can, because a genuine mistake that got approved otherwise
        // has no route out of the books at all. The audit entry below records
        // who did it either way.
        // Approved spending is a financial record: the owner may still remove a genuine mistake, but must say why,
        // and the reason is kept in the audit trail.
        $reason = null;
        if ($expense->status === 'approved') {
            $reason = trim((string) $request->input('reason'));
            if (mb_strlen($reason) < 5) {
                return response()->json(['message' => __('messages.expense_delete_reason')], 422);
            }
        }

        AuditLog::record('expense.deleted', $expense, $expense->toArray(), $reason ? ['reason' => $reason] : []);
        $expense->delete();

        return response()->json(['message' => __('messages.expense_deleted')]);
    }

    /** The owner turns an expense down, with the reason the person who recorded it will see. */
    public function reject(Request $request, Expense $expense): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500'], [
            'reason.required' => __('messages.expense_reject_reason'),
            'reason.min' => __('messages.expense_reject_reason'),
        ]);

        if ($expense->status !== 'pending') {
            return response()->json(['message' => __('messages.expense_is_not_pending')], 422);
        }
        // An expense booked from a supplier bill is decided through that bill.
        if ($expense->supplier_bill_id) {
            return response()->json(['message' => __('messages.expense_from_supplier_bill')], 422);
        }

        $before = $expense->toArray();
        $expense->update(['status' => 'rejected', 'approved_by' => Tenant::actorId(), 'approved_at' => now(), 'rejection_reason' => trim($data['reason'])]);
        AuditLog::record('expense.rejected', $expense, $before, ['status' => 'rejected', 'reason' => $data['reason']]);

        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])));
    }

    public function approve(Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return response()->json(['message' => __('messages.expense_is_not_pending')], 422);
        }

        $before = $expense->toArray();
        $expense->update([
            'status' => 'approved',
            'approved_by' => Tenant::actorId(),
            'approved_at' => now(),
        ]);
        AuditLog::record('expense.approved', $expense, $before, ['status' => 'approved']);

        return response()->json((new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])))->resolve() + ['budget_warning' => app(BudgetActuals::class)->warningFor($expense)]);
    }
}
