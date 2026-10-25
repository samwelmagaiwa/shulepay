<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\SyncClassSubjectsRequest;
use App\Models\ExamResult;
use App\Models\Subject;
use App\Services\Exam\ExamScope;
use Illuminate\Http\JsonResponse;

/** Which subjects each class sits. */
class ClassSubjectController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = ExamScope::classes()
            ->with('subjects:subjects.id')
            ->orderBy('school_classes.sort_order')
            ->orderBy('school_classes.name')
            ->get()
            ->map(fn ($class) => [
                'id' => $class->id,
                'label' => ExamScope::classLabel($class),
                'subject_ids' => $class->subjects->pluck('id')->values()->all(),
            ]);

        return response()->json(['data' => $classes]);
    }

    /**
     * Replace the class's subjects. Removing a subject that already has results
     * for the class's current pupils is refused, because those results would
     * silently drop off every report card.
     */
    public function sync(SyncClassSubjectsRequest $request, string $class): JsonResponse
    {
        $schoolClass = ExamScope::classes()->findOrFail($class);
        $wanted = collect($request->validated('subject_ids'))->map(fn ($id) => (int) $id);
        $removed = $schoolClass->subjects()->pluck('subjects.id')->diff($wanted);

        if ($removed->isNotEmpty()) {
            $blocked = ExamResult::query()
                ->whereIn('subject_id', $removed)
                ->whereIn('student_id', ExamScope::activeEnrollments()
                    ->where('enrollments.school_class_id', $schoolClass->id)
                    ->select('enrollments.student_id'))
                ->distinct()
                ->pluck('subject_id');

            if ($blocked->isNotEmpty()) {
                $names = Subject::whereIn('id', $blocked)->orderBy('name')->pluck('name')->implode(', ');

                return response()->json([
                    'message' => __('exams.class_subjects.cannot_remove', ['names' => $names, 'class' => ExamScope::classLabel($schoolClass)]),
                    'code' => 'SUBJECT_HAS_RESULTS',
                    'details' => ['subject_ids' => $blocked->values()->all()],
                ], 409);
            }
        }

        $schoolClass->subjects()->sync($wanted->all());

        return response()->json([
            'message' => __('exams.class_subjects.now_has', ['class' => ExamScope::classLabel($schoolClass), 'count' => $wanted->count()]),
            'data' => ['id' => $schoolClass->id, 'subject_ids' => $wanted->values()->all()],
        ]);
    }
}
