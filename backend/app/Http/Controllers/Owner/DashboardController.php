<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DashboardService;
use App\Support\DashboardPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $request->integer('school_id')
            ?: (int) $request->header('X-School-Id')
            ?: $user->school_id;

        $stats = $this->service->stats($schoolId ?: null);

        // Money figures are withheld here rather than hidden in the browser, so a
        // locked dashboard has nothing to recover from the network response.
        if (DashboardPrivacy::isLocked($user)) {
            $stats = DashboardPrivacy::redact($stats);
        }

        return response()->json($stats);
    }
}
