<?php

namespace App\Http\Controllers\Api\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ExamRequest;
use App\Http\Resources\Exam\ExamResource;
use App\Models\Exam;
use App\Models\ExamMarkChange;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\Exam\ExamCommentService;
use App\Services\Exam\ExamException;
use App\Services\Exam\ExamProgressService;
use App\Services\Exam\ExamReadinessService;
use App\Services\Exam\ExamResultsService;
use App\Services\Exam\ExamScope;
use App\Services\Exam\ExamWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamWorkflowService $workflow,
        private readonly ExamResultsService $results,
    ) {}

    /** Which setup steps are done, and which one comes next. */
    public function readiness(ExamReadinessService $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->check()]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'term_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Exam::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $exams = Exam::query()
            ->with(['term.academicYear', 'classes'])
            ->withCount('results')
            ->when($filters['term_id'] ?? null, fn ($q, $termId) => $q->where('term_id', $termId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(20);

        // How many exams there are in each state (for the cards at the top), whatever the filters say.
        $counts = Exam::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ExamResource::collection($exams)->additional(['counts' => [
            'all' => (int) $counts->sum(),
            'draft' => (int) ($counts['draft'] ?? 0),
            'open' => (int) ($counts['open'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
        ]]);
    }

    public function store(ExamRequest $request): JsonResponse
    {
        $data = $request->validated();
        $term = Term::findOrFail($data['term_id']);

        $exam = DB::transaction(function () use ($data, $term, $request) {
            $exam = Exam::create($this->attributes($data) + [
                'academic_year_id' => $term->academic_year_id,
                'status' => Exam::STATUS_DRAFT,
                'created_by' => $request->user()->id,
            ]);
            $exam->classes()->sync($data['all_classes'] ? [] : $data['class_ids']);

            return $exam;
        });

        return response()->json([
            'message' => __('exams.created', ['name' => $exam->name]),
            'data' => new ExamResource($exam->load(['term.academicYear', 'classes'])),
        ], 201);
    }

    public function show(string $exam, ExamCommentService $comments): JsonResponse
    {
        $exam = $this->find($exam);
        $completeness = $this->results->completeness($exam);
        $commentStatus = $comments->status($exam);

        return response()->json([
            'data' => new ExamResource($exam->load(['term.academicYear', 'classes'])),
            'completeness' => $completeness,
            'comments' => $commentStatus,
            'can_publish' => $exam->isOpen() && $completeness['entered'] > 0 && $completeness['missing'] === 0
                && (! $exam->require_comments || $commentStatus['complete']),
        ]);
    }

    public function update(ExamRequest $request, string $exam): JsonResponse
    {
        $exam = $this->find($exam);
        $this->workflow->assertNotPublished($exam, 'edited');

        $data = $request->validated();
        $term = Term::findOrFail($data['term_id']);

        $outOf = (int) ($data['out_of'] ?? $exam->out_of ?? 100);
        if ($outOf !== (int) $exam->out_of && $exam->results()->exists()) {
            throw ExamException::invalid(__('exams.err.out_of_locked'), 'OUT_OF_LOCKED');
        }

        DB::transaction(function () use ($exam, $data, $term) {
            $classIds = $data['all_classes'] ? [] : $data['class_ids'];
            $this->workflow->assertClassesCanBeDropped($exam, $data['all_classes'], $classIds);

            $exam->update($this->attributes($data, $exam) + ['academic_year_id' => $term->academic_year_id]);
            $exam->classes()->sync($classIds);
        });

        return response()->json([
            'message' => __('exams.updated', ['name' => $exam->name]),
            'data' => new ExamResource($exam->load(['term.academicYear', 'classes'])),
        ]);
    }

    public function destroy(string $exam): JsonResponse
    {
        $exam = $this->find($exam);
        $this->workflow->assertNotPublished($exam, 'deleted');

        $name = $exam->name;
        $exam->delete();

        return response()->json(['message' => __('exams.deleted', ['name' => $name])]);
    }

    public function open(string $exam): JsonResponse
    {
        $exam = $this->workflow->open($this->find($exam));

        return response()->json([
            'message' => __('exams.opened', ['name' => $exam->name]),
            'data' => new ExamResource($exam->load('term.academicYear')),
        ]);
    }

    public function publish(Request $request, string $exam): JsonResponse
    {
        $data = $request->validate(['sms_parents' => ['nullable', 'boolean']]);
        $published = $this->workflow->publish($this->find($exam), $request->user()->id, (bool) ($data['sms_parents'] ?? false));

        return response()->json([
            'message' => __('exams.published', ['name' => $published->name]),
            'sms_sent' => $this->workflow->smsSent,
            'data' => new ExamResource($published->load('term.academicYear')),
        ]);
    }

    public function unpublish(Request $request, string $exam): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], [
            'reason.required' => __('exams.validation.reason_required'),
            'reason.min' => __('exams.validation.reason_required'),
        ]);
        $unpublished = $this->workflow->unpublish($this->find($exam), $data['reason'], $request->user()->id);

        return response()->json([
            'message' => __('exams.unpublished', ['name' => $unpublished->name]),
            'data' => new ExamResource($unpublished->load('term.academicYear')),
        ]);
    }

    /** Class-by-subject marks progress, with who teaches what and who has submitted. */
    public function progress(ExamProgressService $progress, string $exam): JsonResponse
    {
        return response()->json(['data' => $progress->grid($this->find($exam))]);
    }

    /** Remind the teachers who still owe marks. */
    public function remind(ExamProgressService $progress, string $exam): JsonResponse
    {
        $exam = $this->find($exam);
        $count = $progress->remind($exam);

        return response()->json([
            'sent' => $count,
            'message' => $count ? __('exams.remind.sent', ['count' => $count]) : __('exams.remind.none'),
        ]);
    }

    /** The audit trail of changed marks, newest first. */
    public function markChanges(Request $request, string $exam): JsonResponse
    {
        $exam = $this->find($exam);
        $changes = ExamMarkChange::where('exam_id', $exam->id)
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', $request->integer('subject_id')))
            ->latest('id')->limit(200)->get();

        $students = Student::whereIn('id', $changes->pluck('student_id'))->get()->keyBy('id');
        $subjects = Subject::whereIn('id', $changes->pluck('subject_id'))->get()->keyBy('id');

        return response()->json(['data' => $changes->map(fn ($c) => [
            'id' => $c->id,
            'student' => $students->get($c->student_id) ? ExamScope::personName($students->get($c->student_id)) : '—',
            'subject' => $subjects->get($c->subject_id)?->name ?? '—',
            'old_marks' => $c->old_marks,
            'new_marks' => $c->new_marks,
            'old_absent' => $c->old_absent,
            'new_absent' => $c->new_absent,
            'changed_by' => $c->changed_by_name,
            'at' => $c->created_at?->toIso8601String(),
        ])->values()]);
    }

    /**
     * Looked up here rather than by route-model binding: binding runs before the
     * active school is set, so it would resolve another school's exam.
     */
    private function find(string $id): Exam
    {
        return Exam::findOrFail($id);
    }

    /** The columns of an exam from validated input; optional ones keep their value (or default) when left out. */
    private function attributes(array $data, ?Exam $current = null): array
    {
        $attributes = Arr::except($data, 'class_ids');
        $attributes['out_of'] = (int) ($data['out_of'] ?? $current?->out_of ?? 100);
        $attributes['hold_for_fees'] = (bool) ($data['hold_for_fees'] ?? $current?->hold_for_fees ?? false);
        $attributes['require_comments'] = (bool) ($data['require_comments'] ?? $current?->require_comments ?? false);

        return $attributes;
    }
}
