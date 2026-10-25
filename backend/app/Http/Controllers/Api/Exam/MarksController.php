<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\MarksGridRequest;
use App\Http\Requests\Exam\SaveMarksRequest;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Exam\ExamScope;
use App\Services\Exam\MarksImportService;
use App\Services\Exam\MarksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** The marks-entry grid for one exam, class and subject. */
class MarksController extends Controller
{
    public function __construct(private readonly MarksService $marks) {}

    public function show(MarksGridRequest $request, string $exam): JsonResponse
    {
        return response()->json(['data' => $this->marks->grid(
            Exam::findOrFail($exam),
            ExamScope::classes()->findOrFail($request->integer('school_class_id')),
            Subject::findOrFail($request->integer('subject_id')),
            $request->user(),
        )]);
    }

    public function update(SaveMarksRequest $request, string $exam): JsonResponse
    {
        // Not route-model bound: binding runs before the active school is set.
        $exam = Exam::findOrFail($exam);
        $class = ExamScope::classes()->findOrFail($request->integer('school_class_id'));
        $subject = Subject::findOrFail($request->integer('subject_id'));

        $outcome = $this->marks->save($exam, $class, $subject, $request->validated('entries'), $request->user());

        return response()->json([
            'message' => $this->summary($outcome, $subject),
            'saved' => $outcome['saved'],
            'cleared' => $outcome['cleared'],
            'data' => $this->marks->grid($exam, $class, $subject),
        ]);
    }

    /** The teacher hands the marks in. */
    public function submit(MarksGridRequest $request, string $exam): JsonResponse
    {
        $exam = Exam::findOrFail($exam);
        [$class, $subject] = $this->target($request);
        $this->marks->submit($exam, $class, $subject, $request->user());

        return response()->json([
            'message' => __('exams.marks.submitted', ['subject' => $subject->name, 'class' => ExamScope::classLabel($class)]),
            'data' => $this->marks->grid($exam, $class, $subject),
        ]);
    }

    /** A coordinator or head opens handed-in marks for changes again. */
    public function reopen(MarksGridRequest $request, string $exam): JsonResponse
    {
        $exam = Exam::findOrFail($exam);
        [$class, $subject] = $this->target($request);
        $this->marks->reopen($exam, $class, $subject);

        return response()->json([
            'message' => __('exams.marks.reopened', ['subject' => $subject->name, 'class' => ExamScope::classLabel($class)]),
            'data' => $this->marks->grid($exam, $class, $subject),
        ]);
    }

    /** A spreadsheet to fill in: the class list with any marks already entered. */
    public function template(MarksGridRequest $request, MarksImportService $import, string $exam): Response
    {
        $exam = Exam::findOrFail($exam);
        [$class, $subject] = $this->target($request);

        return response($import->template($exam, $class, $subject, $request->user()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="marks-'.Str::slug($subject->name.' '.ExamScope::classLabel($class)).'.csv"',
        ]);
    }

    /** Import a filled-in sheet; nothing is saved unless every row is valid. */
    public function import(MarksGridRequest $request, MarksImportService $import, string $exam): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:2048']]);
        $exam = Exam::findOrFail($exam);
        [$class, $subject] = $this->target($request);

        $outcome = $import->import($exam, $class, $subject, $request->file('file'), $request->user());

        return response()->json([
            'message' => __('exams.marks.imported', ['count' => $outcome['saved'], 'subject' => $subject->name]),
            'saved' => $outcome['saved'],
            'data' => $this->marks->grid($exam, $class, $subject),
        ]);
    }

    /** @return array{0: SchoolClass, 1: Subject} */
    private function target(Request $request): array
    {
        return [
            ExamScope::classes()->findOrFail($request->integer('school_class_id')),
            Subject::findOrFail($request->integer('subject_id')),
        ];
    }

    private function summary(array $outcome, Subject $subject): string
    {
        $parts = [];
        if ($outcome['saved']) {
            $parts[] = __('exams.marks.saved', ['count' => $outcome['saved']]);
        }
        if ($outcome['cleared']) {
            $parts[] = __('exams.marks.cleared', ['count' => $outcome['cleared']]);
        }

        return __('exams.marks.summary', [
            'parts' => $parts ? implode(', ', $parts) : __('exams.marks.nothing'),
            'subject' => $subject->name,
        ]);
    }
}
