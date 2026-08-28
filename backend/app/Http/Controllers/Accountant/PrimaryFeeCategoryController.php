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

    /**
     * The two class tiers the school prices separately. Order matters here —
     * it's the order both the admin page and the registration form render.
     */
    private const TIERS = ['std_4_6', 'std_1_3'];

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->activeSchoolId($request);

        $existing = PrimaryFeeCategory::allSchools()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->get()
            ->groupBy('class_tier');

        $tiers = [];
        foreach (self::TIERS as $tier) {
            $existingForTier = ($existing[$tier] ?? collect())->keyBy('category');
            $tiers[$tier] = array_map(fn ($category) => [
                'category' => $category,
                'amount_cents' => $existingForTier[$category]->amount_cents
                    ?? PrimaryFeeCategory::DEFAULT_AMOUNTS_CENTS[$tier][$category],
            ], self::CATEGORIES);
        }

        return response()->json(['tiers' => $tiers]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tiers' => 'required|array',
            'tiers.*' => 'array',
            'tiers.*.*.category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'tiers.*.*.amount_cents' => 'required|integer|min:0',
        ]);

        $schoolId = $this->activeSchoolId($request);
        abort_if(! $schoolId, 422, 'No active school selected.');

        foreach ($data['tiers'] as $tier => $rows) {
            if (! in_array($tier, self::TIERS, true)) {
                continue;
            }
            foreach ($rows as $row) {
                PrimaryFeeCategory::allSchools()->updateOrCreate(
                    ['school_id' => $schoolId, 'class_tier' => $tier, 'category' => $row['category']],
                    ['amount_cents' => $row['amount_cents']]
                );
            }
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
