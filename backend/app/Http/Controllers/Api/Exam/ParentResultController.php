<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Resources\Exam\ExamResource;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Exam\ExamResultsService;
use App\Services\Exam\ExamScope;
use App\Services\Pdf\ExamReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A parent's read-only view of their own child's published results. Anything not
 * yet published — or belonging to someone else's child — is invisible.
 */
class ParentResultController extends Controller
{
    public function __construct(
        private readonly ExamResultsService $results,
        private readonly ExamReportPdf $pdf,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $student = $this->ownChild($request);

        $exams = Exam::query()
            ->with('term.academicYear')
            ->where('status', Exam::STATUS_PUBLISHED)
            ->whereHas('results', fn ($q) => $q->where('student_id', $student->id))
            ->latest('published_at')
            ->limit(50)
            ->get();

        return response()->json([
            'student' => ['id' => $student->id, 'name' => ExamScope::personName($student)],
            'data' => $exams->map(fn (Exam $exam) => [
                ...(new ExamResource($exam))->resolve(),
                'held' => $this->results->feesFor($exam, $student->id)['hold'],
            ])->values(),
        ]);
    }

    public function show(Request $request, string $exam): JsonResponse
    {
        return response()->json(['data' => $this->card($request, $exam)]);
    }

    public function pdf(Request $request, string $exam): Response
    {
        $card = $this->card($request, $exam);

        return response($this->pdf->reportCard($card), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Report-Card-'
                .Str::slug($card['student']['admission_number'] ?: $card['student']['full_name']).'.pdf"',
        ]);
    }

    private function card(Request $request, string $examId): array
    {
        $student = $this->ownChild($request);
        // Not route-model bound: binding runs before the active school is set.
        $exam = Exam::findOrFail($examId);

        abort_unless($exam->isPublished(), 404, __('messages.these_results_have_not_been_published_yet'));
        abort_unless(
            $exam->results()->where('student_id', $student->id)->exists(),
            404,
            __('exams.parent.no_results'),
        );
        abort_if($this->results->feesFor($exam, $student->id)['hold'], 403, __('exams.err.fees_hold'));

        return $this->results->reportCard($exam, $student);
    }

    private function ownChild(Request $request): Student
    {
        $request->validate(['student_id' => ['required', 'integer']]);

        $guardian = $request->user()->guardian;
        $student = $guardian?->students()->where('students.id', $request->integer('student_id'))->first();

        abort_unless($student, 403, __('messages.access_denied_this_student_is_not_your'));

        return $student;
    }
}
