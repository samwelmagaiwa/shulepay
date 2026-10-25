<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendanceHistoryService;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only attendance history for the roles that take registers. Everything is limited to the
 * schools the signed-in user may see; asking for a class outside them is a 404.
 */
class AttendanceHistoryController extends Controller
{
    private function scoped(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'school_class_id' => 'nullable|integer',
        ]);

        $ids = SchoolScope::ids($request->user());
        $service = new AttendanceHistoryService($ids);
        [$from, $to] = AttendanceHistoryService::clampRange($request->input('from'), $request->input('to'));

        $classId = $request->filled('school_class_id') ? $request->integer('school_class_id') : null;
        abort_if($classId && $service->classes($classId)->isEmpty(), 404, __('Class not found.'));

        return [$service, $from, $to, $classId];
    }

    public function index(Request $request): JsonResponse
    {
        [$service, $from, $to, $classId] = $this->scoped($request);

        return response()->json(['data' => $service->overview($from, $to, $classId)]);
    }

    public function absentees(Request $request): JsonResponse
    {
        [$service, $from, $to, $classId] = $this->scoped($request);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'absentees' => $service->absentees($from, $to, $classId),
        ]]);
    }

    public function pupils(Request $request): JsonResponse
    {
        [$service, $from, $to, $classId] = $this->scoped($request);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'low_rate' => AttendanceHistoryService::LOW_RATE,
            'pupils' => $service->pupils($from, $to, $classId),
        ]]);
    }
}
