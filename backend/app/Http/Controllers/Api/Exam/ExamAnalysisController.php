<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ClassResultsRequest;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Exam\ExamAnalysisService;
use App\Services\Exam\ExamScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** What the marks say: class analysis, a weighted result over several exams, and one pupil's history. */
class ExamAnalysisController extends Controller
{
    public function __construct(private readonly ExamAnalysisService $analysis) {}

    public function show(ClassResultsRequest $request, string $exam): JsonResponse
    {
        return response()->json(['data' => $this->analysis->analyse(
            Exam::findOrFail($exam),
            ExamScope::classes()->findOrFail($request->integer('school_class_id')),
        )]);
    }

    /** GET exams/combined?school_class_id=&exams[0][id]=&exams[0][weight]=... */
    public function combined(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'exams' => ['required', 'array', 'min:2', 'max:6'],
            'exams.*.id' => ['required', 'integer', 'distinct'],
            'exams.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $weights = collect($data['exams'])->mapWithKeys(fn ($e) => [(int) $e['id'] => (float) $e['weight']])->all();

        return response()->json(['data' => $this->analysis->combined(
            ExamScope::classes()->findOrFail($data['school_class_id']),
            $weights,
        )]);
    }

    public function history(string $student): JsonResponse
    {
        return response()->json(['data' => $this->analysis->studentHistory(Student::findOrFail($student))]);
    }
}
