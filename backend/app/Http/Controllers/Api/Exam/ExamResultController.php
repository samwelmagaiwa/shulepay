<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ClassResultsRequest;
use App\Models\Exam;
use App\Models\Student;
use App\Services\Exam\ExamResultsService;
use App\Services\Exam\ExamScope;
use App\Services\Pdf\ExamReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Staff-facing results: class summaries and report cards (JSON and PDF). */
class ExamResultController extends Controller
{
    public function __construct(
        private readonly ExamResultsService $results,
        private readonly ExamReportPdf $pdf,
    ) {}

    public function classResults(ClassResultsRequest $request, string $exam): JsonResponse
    {
        return response()->json(['data' => $this->results->classResults(Exam::findOrFail($exam), $this->schoolClass($request))]);
    }

    public function classResultsPdf(ClassResultsRequest $request, string $exam): Response
    {
        $exam = Exam::findOrFail($exam);
        $results = $this->results->classResults($exam, $this->schoolClass($request));

        return $this->download(
            $this->pdf->classResults($results),
            'Class-Results-'.Str::slug($exam->name.' '.$results['class']['label']),
        );
    }

    public function reportCard(string $exam, Student $student): JsonResponse
    {
        return response()->json(['data' => $this->results->reportCard(Exam::findOrFail($exam), $student)]);
    }

    public function reportCardPdf(string $exam, Student $student): Response
    {
        $card = $this->results->reportCard(Exam::findOrFail($exam), $student);

        return $this->download(
            $this->pdf->reportCard($card),
            'Report-Card-'.Str::slug($card['student']['admission_number'] ?: $card['student']['full_name']),
        );
    }

    /** Every report card of a class in one document, one per page. */
    public function classReportCardsPdf(ClassResultsRequest $request, string $exam): Response
    {
        $exam = Exam::findOrFail($exam);
        $class = $this->schoolClass($request);
        $cards = $this->results->classReportCards($exam, $class);

        // a batch: only the chosen pupils (the cards keep their alphabetical order)
        if ($ids = $request->input('student_ids')) {
            $cards = array_values(array_filter($cards, fn (array $card) => in_array($card['student']['id'], array_map('intval', $ids), true)));
        }

        abort_if($cards === [], 422, __('messages.this_class_has_no_enrolled_students_to'));

        return $this->download(
            $this->pdf->reportCards($cards),
            'Report-Cards-'.Str::slug($exam->name.' '.ExamScope::classLabel($class)),
        );
    }

    private function schoolClass(ClassResultsRequest $request)
    {
        return ExamScope::classes()->findOrFail($request->integer('school_class_id'));
    }

    private function download(string $content, string $name): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"',
        ]);
    }
}
