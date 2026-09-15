<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ClassFeeCollection;
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

    /**
     * Students behind the dashboard's "Not linked to a class" figure. Read only.
     *
     * Money, so it obeys the same privacy lock as the stats it explains:
     * a locked dashboard must not be readable through a side endpoint.
     */
    public function unassignedFees(Request $request, ClassFeeCollection $collection): JsonResponse
    {
        $user = $request->user();

        if (DashboardPrivacy::isLocked($user)) {
            return response()->json(['message' => 'Dashboard figures are locked.', 'locked' => true], 423);
        }

        return response()->json($collection->unassigned($this->schoolId($request)));
    }

    /** The school in view: explicit parameter, then header, then the user's own. */
    private function schoolId(Request $request): ?int
    {
        $id = $request->integer('school_id')
            ?: (int) $request->header('X-School-Id')
            ?: $request->user()->school_id;

        return $id ?: null;
    }
}
