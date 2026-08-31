<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query();

        if ($school = app()->bound('active_school') ? app('active_school') : null) {
            $query->where(function ($q) use ($school) {
                $q->whereNull('school_id')->orWhere('school_id', $school->id);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:operational,capital,staff,other',
            'description' => 'nullable|string|max:500',
        ]);

        if ($school = app()->bound('active_school') ? app('active_school') : null) {
            $data['school_id'] = $school->id;
        }

        $category = ExpenseCategory::create($data);
        AuditLog::record('expense_category.created', $category, [], $data);

        return response()->json($category, 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:operational,capital,staff,other',
            'description' => 'nullable|string|max:500',
        ]);

        $before = $expenseCategory->toArray();
        $expenseCategory->update($data);
        AuditLog::record('expense_category.updated', $expenseCategory, $before, $data);

        return response()->json($expenseCategory);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // expenses.category_id is a plain constrained() foreign key, so MySQL
        // RESTRICTs the delete and throws — which surfaced as a bare HTTP 500
        // "Server Error". Checking first turns that into an answer the person
        // clicking can act on, and names the number of expenses in the way.
        $inUse = $expenseCategory->expenses()->count();

        if ($inUse > 0) {
            return response()->json([
                'message' => "This category is used by {$inUse} expense(s) and cannot be deleted. "
                    .'Re-file those expenses under another category first.',
                'expenses_count' => $inUse,
            ], 422);
        }

        // Recorded only once the delete is certain to proceed. Previously this
        // ran first, so a delete blocked by the database still wrote an audit
        // entry claiming the category had been deleted.
        AuditLog::record('expense_category.deleted', $expenseCategory, $expenseCategory->toArray(), []);
        $expenseCategory->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
