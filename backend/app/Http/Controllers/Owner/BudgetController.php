<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Support\SchoolScope;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $budgets = Budget::with(['academicYear', 'items'])->latest()->paginate(20);

        return BudgetResource::collection($budgets);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'name' => 'required|string|max:255',
            'items' => 'sometimes|array',
            'items.*.category' => 'required_with:items|string|max:255',
            'items.*.description' => 'required_with:items|string|max:500',
            'items.*.planned_cents' => 'required_with:items|integer|min:0',
            'items.*.kind' => 'sometimes|in:expense,refunds',
            'items.*.expense_category_id' => 'sometimes|nullable|exists:expense_categories,id',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $schoolId = AcademicYear::findOrFail($data['academic_year_id'])->school_id
                ?? auth()->user()->school_id;
            // The year may belong to another school than the user's: only budget for schools they work in.
            SchoolScope::authorize($request->user(), $schoolId ? (int) $schoolId : null);

            $budget = Budget::create(Tenant::stripSchoolId([
                'academic_year_id' => $data['academic_year_id'],
                'school_id' => $schoolId,
                'name' => $data['name'],
                'status' => 'draft',
            ]));

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    BudgetItem::create([
                        'budget_id' => $budget->id,
                        'category' => $item['category'],
                        'description' => $item['description'],
                        'planned_cents' => $item['planned_cents'],
                        'actual_cents' => 0,
                        'kind' => $item['kind'] ?? 'expense',
                        'expense_category_id' => $item['expense_category_id'] ?? null,
                    ]);
                }
            }

            AuditLog::record('budget.created', $budget, [], $data);

            return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])), 201);
        });
    }

    public function show(Budget $budget): JsonResponse
    {
        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }

    public function update(Request $request, Budget $budget): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'items' => 'sometimes|array',
            'items.*.id' => 'sometimes|exists:budget_items,id',
            'items.*.category' => 'required_with:items|string|max:255',
            'items.*.description' => 'required_with:items|string|max:500',
            'items.*.planned_cents' => 'required_with:items|integer|min:0',
            'items.*.actual_cents' => 'sometimes|integer|min:0',
            'items.*.kind' => 'sometimes|in:expense,refunds',
            'items.*.expense_category_id' => 'sometimes|nullable|exists:expense_categories,id',
        ]);

        return DB::transaction(function () use ($data, $budget) {
            $before = $budget->toArray();

            if (isset($data['name'])) {
                $budget->update(['name' => $data['name']]);
            }

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        BudgetItem::where('id', $itemData['id'])
                            ->where('budget_id', $budget->id)
                            ->update(array_filter([
                                'category' => $itemData['category'] ?? null,
                                'description' => $itemData['description'] ?? null,
                                'planned_cents' => $itemData['planned_cents'] ?? null,
                                'actual_cents' => $itemData['actual_cents'] ?? null,
                                'kind' => $itemData['kind'] ?? null,
                            ], fn ($v) => $v !== null) + ['expense_category_id' => $itemData['expense_category_id'] ?? null]);
                    } else {
                        BudgetItem::create([
                            'budget_id' => $budget->id,
                            'category' => $itemData['category'],
                            'description' => $itemData['description'],
                            'planned_cents' => $itemData['planned_cents'],
                            'actual_cents' => $itemData['actual_cents'] ?? 0,
                            'kind' => $itemData['kind'] ?? 'expense',
                            'expense_category_id' => $itemData['expense_category_id'] ?? null,
                        ]);
                    }
                }
            }

            AuditLog::record('budget.updated', $budget, $before, $data);

            return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
        });
    }

    public function destroy(Budget $budget): JsonResponse
    {
        AuditLog::record('budget.deleted', $budget, $budget->toArray(), []);
        $budget->items()->delete();
        $budget->delete();

        return response()->json(null, 204);
    }

    /** Remove one line of a budget (a closed budget is history and stays as it is). */
    public function destroyItem(Budget $budget, BudgetItem $item): JsonResponse
    {
        abort_unless($item->budget_id === $budget->id, 404);
        if ($budget->status === 'closed') {
            return response()->json(['message' => __('messages.budget_closed_locked')], 422);
        }

        AuditLog::record('budget.line_deleted', $budget, $item->toArray(), []);
        $item->delete();

        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }

    public function activate(Budget $budget): JsonResponse
    {
        if ($budget->status !== 'draft') {
            return response()->json(['message' => __('messages.only_draft_budgets_can_be_activated')], 422);
        }

        $before = $budget->toArray();
        $budget->update(['status' => 'active']);
        AuditLog::record('budget.activated', $budget, $before, ['status' => 'active']);

        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }

    public function close(Budget $budget): JsonResponse
    {
        if ($budget->status !== 'active') {
            return response()->json(['message' => __('messages.only_active_budgets_can_be_closed')], 422);
        }

        $before = $budget->toArray();
        $budget->update(['status' => 'closed']);
        AuditLog::record('budget.closed', $budget, $before, ['status' => 'closed']);

        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }
}
