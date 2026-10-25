<?php

namespace App\Http\Controllers\Api\Exam;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ClassResultsRequest;
use App\Models\Exam;
use App\Models\ExamComment;
use App\Models\ExamSignoff;
use App\Models\TeacherAssignment;
use App\Services\Exam\ExamCommentService;
use App\Services\Exam\ExamException;
use App\Services\Exam\ExamResultsService;
use App\Services\Exam\ExamScope;
use App\Services\Exam\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The class teacher's and the head teacher's comments on each pupil's report card. The head's comment
 * can only be written by the people who publish results; everyone else keeps what is already there.
 */
class ExamCommentController extends Controller
{
    public function __construct(
        private readonly ExamResultsService $results,
        private readonly ExamCommentService $comments,
        private readonly TeacherAssignmentService $assignments,
    ) {}

    public function index(ClassResultsRequest $request, string $exam): JsonResponse
    {
        $exam = Exam::findOrFail($exam);
        $class = ExamScope::classes()->findOrFail($request->integer('school_class_id'));
        ExamScope::assertCovers($exam, $class);

        $enrolled = $this->results->enrolled($class);
        $comments = ExamComment::where('exam_id', $exam->id)->whereIn('student_id', $enrolled->pluck('student_id'))->get()->keyBy('student_id');

        return response()->json(['data' => [
            'can_head_comment' => $this->mayWriteHeadComment($request),
            'editable' => ! $exam->isPublished(),
            'students' => $enrolled->map(fn ($e) => [
                'student_id' => $e->student_id,
                'full_name' => ExamScope::personName($e->student),
                'admission_number' => $e->admission_number,
                'teacher_comment' => $comments->get($e->student_id)?->teacher_comment,
                'head_comment' => $comments->get($e->student_id)?->head_comment,
            ])->values(),
        ]]);
    }

    public function update(Request $request, string $exam): JsonResponse
    {
        $exam = Exam::findOrFail($exam);
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.student_id' => ['required', 'integer'],
            'entries.*.teacher_comment' => ['nullable', 'string', 'max:500'],
            'entries.*.head_comment' => ['nullable', 'string', 'max:500'],
        ], [
            'entries.*.teacher_comment.max' => __('exams.validation.comment_max'),
            'entries.*.head_comment.max' => __('exams.validation.comment_max'),
        ]);

        if ($exam->isPublished()) {
            throw ExamException::state(__('exams.state.results_published'), 'EXAM_PUBLISHED');
        }
        $class = ExamScope::classes()->findOrFail($data['school_class_id']);
        ExamScope::assertCovers($exam, $class);
        $this->assertMayComment($request, $exam, $class);

        $enrolledIds = $this->results->enrolled($class)->pluck('student_id');
        $unknown = collect($data['entries'])->pluck('student_id')->diff($enrolledIds);
        if ($unknown->isNotEmpty()) {
            throw ExamException::invalid(__('exams.err.student_not_in_class', ['class' => ExamScope::classLabel($class)]), 'STUDENT_NOT_IN_CLASS');
        }

        $mayHead = $this->mayWriteHeadComment($request);
        foreach ($data['entries'] as $entry) {
            $row = ExamComment::firstOrNew(['exam_id' => $exam->id, 'student_id' => (int) $entry['student_id']]);
            $row->teacher_comment = $this->clean($entry['teacher_comment'] ?? null);
            if ($mayHead) {
                $row->head_comment = $this->clean($entry['head_comment'] ?? null);
            }
            $row->entered_by = $request->user()->id;

            if ($row->teacher_comment === null && $row->head_comment === null) {
                $row->exists && $row->delete();

                continue;
            }
            $row->save();
        }

        return response()->json(['message' => __('exams.comments.saved')]);
    }

    /** The score bands and their ready-made comments. */
    public function bands(): JsonResponse
    {
        return response()->json(['data' => $this->comments->bands()]);
    }

    public function saveBands(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bands' => ['required', 'array', 'min:1', 'max:8'],
            'bands.*.from' => ['required', 'integer', 'between:0,99'],
            'bands.*.teacher' => ['required', 'string', 'max:500'],
            'bands.*.head' => ['required', 'string', 'max:500'],
        ], [
            'bands.*.teacher.required' => __('exams.err.bands_invalid'),
            'bands.*.head.required' => __('exams.err.bands_invalid'),
            'bands.*.from.required' => __('exams.err.bands_invalid'),
        ]);

        return response()->json(['message' => __('exams.comments.bands_saved'), 'data' => $this->comments->saveBands($data['bands'])]);
    }

    /** Where every class stands with its comments and sign-offs. */
    public function status(string $exam): JsonResponse
    {
        return response()->json(['data' => $this->comments->status(Exam::findOrFail($exam))]);
    }

    /** Fill comments from the score bands, for one class or all of them. */
    public function apply(Request $request, string $exam): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:teacher,head'],
            'school_class_id' => ['nullable', 'integer'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $exam = Exam::findOrFail($exam);
        $class = ! empty($data['school_class_id']) ? ExamScope::classes()->findOrFail($data['school_class_id']) : null;

        if ($data['role'] === 'head') {
            abort_unless($this->mayWriteHeadComment($request), 403, __('exams.err.comments_class_not_yours'));
        } elseif ($this->assignments->isRestricted($request->user())) {
            // a classroom teacher fills their own class only
            abort_unless($class, 422, __('exams.err.comments_class_not_yours'));
            $this->assertMayComment($request, $exam, $class);
        }

        $out = $this->comments->apply($exam, $class, $data['role'], (bool) ($data['overwrite'] ?? false), $request->user());

        return response()->json([
            'message' => __('exams.comments.applied', ['count' => $out['applied'], 'kept' => $out['kept']]),
            'data' => $out,
        ]);
    }

    /** The class teacher submits their comments, or the head approves them. */
    public function submit(Request $request, string $exam): JsonResponse
    {
        $data = $request->validate(['school_class_id' => ['required', 'integer'], 'stage' => ['required', 'in:teacher,head']]);
        $exam = Exam::findOrFail($exam);
        $class = ExamScope::classes()->findOrFail($data['school_class_id']);

        if ($data['stage'] === ExamSignoff::HEAD) {
            abort_unless($this->mayWriteHeadComment($request), 403, __('exams.err.comments_class_not_yours'));
        } elseif ($this->assignments->isRestricted($request->user())) {
            $this->assertAssigned($request, $class);
        }

        $this->comments->submit($exam, $class, $data['stage'], $request->user());

        return response()->json([
            'message' => __($data['stage'] === 'head' ? 'exams.comments.approved' : 'exams.comments.submitted', ['class' => ExamScope::classLabel($class)]),
            'data' => $this->comments->status($exam),
        ]);
    }

    /** A head or coordinator takes a sign-off back so the comments can be changed. */
    public function reopen(Request $request, string $exam): JsonResponse
    {
        $data = $request->validate(['school_class_id' => ['required', 'integer'], 'stage' => ['required', 'in:teacher,head']]);
        $exam = Exam::findOrFail($exam);
        $class = ExamScope::classes()->findOrFail($data['school_class_id']);
        $this->comments->reopen($exam, $class, $data['stage']);

        return response()->json(['message' => __('exams.comments.reopened', ['class' => ExamScope::classLabel($class)]), 'data' => $this->comments->status($exam)]);
    }

    /** A classroom teacher comments only in their own classes, and not after submitting. */
    private function assertMayComment(Request $request, Exam $exam, $class): void
    {
        if (! $this->assignments->isRestricted($request->user())) {
            return;
        }
        $this->assertAssigned($request, $class);
        if ($this->comments->isSigned($exam, $class, ExamSignoff::TEACHER)) {
            throw ExamException::forbidden(__('exams.err.comments_locked'), 'COMMENTS_SUBMITTED');
        }
    }

    private function assertAssigned(Request $request, $class): void
    {
        $assigned = TeacherAssignment::where('user_id', $request->user()->id)->where('school_class_id', $class->id)->exists();
        if (! $assigned) {
            throw ExamException::forbidden(__('exams.err.comments_class_not_yours'), 'COMMENTS_CLASS_NOT_YOURS');
        }
    }

    private function mayWriteHeadComment(Request $request): bool
    {
        return $request->user()->hasAnyRole([...UserRole::examPublishers(), UserRole::SuperAdmin->value]);
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
