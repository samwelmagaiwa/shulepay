<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseResource;
use App\Models\AuditLog;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Expense::with(['school', 'category', 'recorder', 'approver'])
            ->latest('expense_date');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->where('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('expense_date', '<=', $request->date_to);
        }

        return ExpenseResource::collection($query->paginate(20));
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

        $data['recorded_by'] = auth()->id();
        $data['status'] = 'pending';

        $expense = Expense::create($data);
        AuditLog::record('expense.created', $expense, [], $data);

        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder'])), 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Only pending expenses can be updated.'], 422);
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

        $before = $expense->toArray();
        $expense->update($data);
        AuditLog::record('expense.updated', $expense, $before, $data);

        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        // Approved spending is a financial record, so it is normally immutable —
        // an accountant cannot remove one after it has been signed off. A
        // superadmin can, because a genuine mistake that got approved otherwise
        // has no route out of the books at all. The audit entry below records
        // who did it either way.
        if ($expense->status !== 'pending' && ! auth()->user()?->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only pending expenses can be deleted. An approved expense can only be removed by a superadmin.',
            ], 422);
        }

        AuditLog::record('expense.deleted', $expense, $expense->toArray(), []);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    public function approve(Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return response()->json(['message' => 'Expense is not pending.'], 422);
        }

        $before = $expense->toArray();
        $expense->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        AuditLog::record('expense.approved', $expense, $before, ['status' => 'approved']);

        return response()->json(new ExpenseResource($expense->load(['school', 'category', 'recorder', 'approver'])));
    }
}
