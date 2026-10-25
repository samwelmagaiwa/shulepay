<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\SubjectRequest;
use App\Models\ExamResult;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subjects = Subject::query()
            ->withCount('classes')
            ->when($request->boolean('active'), fn ($query) => $query->active())
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $subjects]);
    }

    public function store(SubjectRequest $request): JsonResponse
    {
        $subject = Subject::create($request->validated() + ['is_active' => true]);

        return response()->json([
            'message' => __('exams.subject.added', ['name' => $subject->name]),
            'data' => $subject->loadCount('classes'),
        ], 201);
    }

    public function update(SubjectRequest $request, string $subject): JsonResponse
    {
        // Not route-model bound: binding runs before the active school is set.
        $subject = Subject::findOrFail($subject);
        $subject->update($request->validated());

        return response()->json([
            'message' => __('exams.subject.updated', ['name' => $subject->name]),
            'data' => $subject->loadCount('classes'),
        ]);
    }

    public function destroy(string $subject): JsonResponse
    {
        $subject = Subject::findOrFail($subject);
        if (ExamResult::where('subject_id', $subject->id)->exists()) {
            return response()->json([
                'message' => __('exams.subject.has_results', ['name' => $subject->name]),
                'code' => 'SUBJECT_IN_USE',
            ], 409);
        }

        $subject->delete();

        return response()->json(['message' => __('exams.subject.deleted', ['name' => $subject->name])]);
    }
}
