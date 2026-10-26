<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Exam\ExamScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Past registers: a day or a period by class, who was away, and each pupil's attendance. */
class AttendanceHistoryController extends Controller
{
    public function __construct(private readonly AttendanceHistoryService $history) {}

    /** @return array{0: Carbon, 1: Carbon, 2: ?int} */
    private function scope(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'school_class_id' => ['nullable', 'integer'],
        ]);
        $classId = ! empty($data['school_class_id']) ? ExamScope::classes()->findOrFail($data['school_class_id'])->id : null;
        [$from, $to] = AttendanceHistoryService::clampRange($data['from'] ?? null, $data['to'] ?? null);

        return [$from, $to, $classId];
    }

    public function index(Request $request): JsonResponse
    {
        [$from, $to, $classId] = $this->scope($request);

        return response()->json(['data' => $this->history->overview($from, $to, $classId)]);
    }

    public function pupils(Request $request): JsonResponse
    {
        [$from, $to, $classId] = $this->scope($request);

        return response()->json(['data' => $this->history->pupils($from, $to, $classId)]);
    }

    public function absentees(Request $request): JsonResponse
    {
        [$from, , $classId] = $this->scope($request);

        return response()->json(['data' => ['date' => $from->toDateString(), 'absentees' => $this->history->absentees($from, $classId)]]);
    }
}
