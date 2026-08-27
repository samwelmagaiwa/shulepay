<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\PrimaryFeeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrimaryFeeCategoryController extends Controller
{
    /**
     * The 4 fixed categories every school configures amounts for — this list
     * (not the DB) is the source of truth for which categories exist, since
     * new schools have no rows yet and still need to see all 4 to fill in.
     */
    private const CATEGORIES = ['day_transport_food', 'hostel', 'day_food_only', 'day_none'];

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->activeSchoolId($request);

        $existing = PrimaryFeeCategory::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get()
            ->keyBy('category');

        $rows = array_map(fn ($category) => [
            'category' => $category,
            'amount_cents' => $existing[$category]->amount_cents
                ?? PrimaryFeeCategory::DEFAULT_AMOUNTS_CENTS[$category],
        ], self::CATEGORIES);

        return response()->json(['categories' => $rows]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => 'required|array',
            'categories.*.category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'categories.*.amount_cents' => 'required|integer|min:0',
        ]);

        $schoolId = $this->activeSchoolId($request);
        abort_if(! $schoolId, 422, 'No active school selected.');

        foreach ($data['categories'] as $row) {
            PrimaryFeeCategory::allSchools()->updateOrCreate(
                ['school_id' => $schoolId, 'category' => $row['category']],
                ['amount_cents' => $row['amount_cents']]
            );
        }

        return $this->index($request);
    }

    private function activeSchoolId(Request $request): ?int
    {
        if ($request->filled('school_id')) {
            return $request->integer('school_id');
        }

        return app()->bound('active_school') ? app('active_school')?->id : null;
    }
}
