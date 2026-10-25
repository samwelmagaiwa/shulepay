<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\UpdateGradingScaleRequest;
use App\Services\Exam\GradingService;
use Illuminate\Http\JsonResponse;

class GradingScaleController extends Controller
{
    public function __construct(private readonly GradingService $grading) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    /**
     * Published exams keep the scale they were published with, so replacing the
     * scale never changes a report card a parent has already seen.
     */
    public function update(UpdateGradingScaleRequest $request): JsonResponse
    {
        $this->grading->replaceBands($request->validated('bands'));

        return response()->json([
            'message' => __('messages.the_grading_scale_was_saved_published_results'),
            'data' => $this->payload(),
        ]);
    }

    private function payload(): array
    {
        return [
            'bands' => $this->grading->bands()->map(fn ($band) => $band->only(['grade', 'min_marks', 'points', 'remark']))->values(),
            'ranges' => $this->grading->ranges(),
        ];
    }
}
