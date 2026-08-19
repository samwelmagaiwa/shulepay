<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function stats(Request $request): JsonResponse
    {
        $user = auth()->user();
        $schoolId = $request->integer('school_id') ?: $user->school_id;

        return response()->json($this->service->stats($schoolId ?: null));
    }
}
