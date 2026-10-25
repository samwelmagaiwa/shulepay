<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Exam\ExamScope;
use App\Services\Exam\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Who teaches which subject in which class. */
class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly TeacherAssignmentService $assignments) {}

    /** The signed-in user's own marks-entry permissions. */
    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->assignments->forUser($request->user())]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->assignments->teachers()->values()]);
    }

    public function sync(Request $request, string $user): JsonResponse
    {
        $data = $request->validate([
            'assignments' => ['present', 'array', 'max:500'],
            'assignments.*.school_class_id' => ['required', 'integer'],
            'assignments.*.subject_id' => ['required', 'integer'],
        ]);

        // Not route-model bound: binding runs before the active school is set.
        $teacher = ExamScope::whereSchool(User::query(), 'users.school_id')->findOrFail($user);
        $count = $this->assignments->sync($teacher, $data['assignments']);

        return response()->json([
            'message' => __('exams.teachers.saved', ['name' => $teacher->name, 'count' => $count]),
            'data' => ['id' => $teacher->id, 'count' => $count],
        ]);
    }
}
