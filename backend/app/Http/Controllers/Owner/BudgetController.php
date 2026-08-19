<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetItem;
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
        ]);

        return DB::transaction(function () use ($data) {
            $schoolId = AcademicYear::findOrFail($data['academic_year_id'])->school_id
                ?? auth()->user()->school_id;

            $budget = Budget::create([
                'academic_year_id' => $data['academic_year_id'],
                'school_id' => $schoolId,
                'name' => $data['name'],
                'status' => 'draft',
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    BudgetItem::create([
                        'budget_id' => $budget->id,
                        'category' => $item['category'],
                        'description' => $item['description'],
                        'planned_cents' => $item['planned_cents'],
                        'actual_cents' => 0,
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
                            ], fn ($v) => $v !== null));
                    } else {
                        BudgetItem::create([
                            'budget_id' => $budget->id,
                            'category' => $itemData['category'],
                            'description' => $itemData['description'],
                            'planned_cents' => $itemData['planned_cents'],
                            'actual_cents' => $itemData['actual_cents'] ?? 0,
                        ]);
                    }
                }
            }

            AuditLog::record('budget.updated', $budget, $before, $data);

            return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
        });
    }

    public function activate(Budget $budget): JsonResponse
    {
        if ($budget->status !== 'draft') {
            return response()->json(['message' => 'Only draft budgets can be activated.'], 422);
        }

        $before = $budget->toArray();
        $budget->update(['status' => 'active']);
        AuditLog::record('budget.activated', $budget, $before, ['status' => 'active']);

        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }

    public function close(Budget $budget): JsonResponse
    {
        if ($budget->status !== 'active') {
            return response()->json(['message' => 'Only active budgets can be closed.'], 422);
        }

        $before = $budget->toArray();
        $budget->update(['status' => 'closed']);
        AuditLog::record('budget.closed', $budget, $before, ['status' => 'closed']);

        return response()->json(new BudgetResource($budget->load(['academicYear', 'items'])));
    }
}
