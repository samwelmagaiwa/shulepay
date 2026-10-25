<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamComment;
use App\Models\ExamResult;
use App\Models\ExamSignoff;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\Tenant;

/**
 * Report-card comments without writing one per pupil.
 *
 * A school keeps a few score bands (by default 0-39, 40-69, 70-100), each with a ready comment for the class
 * teacher and one for the head. "Apply" fills every pupil in a band at once; single comments can still be
 * edited. Each class then goes through two sign-offs, class teacher first and head second, and an exam that
 * requires comments cannot be published until every class has both.
 */
class ExamCommentService
{
    /** Bands out of the box; the wording comes from the language files until the school edits it. */
    private const DEFAULTS = [['from' => 0, 'key' => 'low'], ['from' => 40, 'key' => 'mid'], ['from' => 70, 'key' => 'high']];

    public function __construct(private readonly ExamResultsService $results) {}

    // ── bands ────────────────────────────────────────────────────────────────

    /** @return list<array{from: int, to: int, teacher: string, head: string, custom: bool}> */
    public function bands(): array
    {
        $stored = Tenant::school()?->settings['exams']['comment_bands'] ?? null;
        $rows = is_array($stored) && $stored
            ? array_map(fn ($b) => ['from' => (int) $b['from'], 'teacher' => (string) $b['teacher'], 'head' => (string) $b['head'], 'custom' => true], $stored)
            : array_map(fn ($b) => ['from' => $b['from'], 'teacher' => __('exams.bands.'.$b['key'].'.teacher'), 'head' => __('exams.bands.'.$b['key'].'.head'), 'custom' => false], self::DEFAULTS);

        usort($rows, fn ($a, $b) => $a['from'] <=> $b['from']);
        foreach ($rows as $i => &$row) {
            $row['to'] = isset($rows[$i + 1]) ? $rows[$i + 1]['from'] - 1 : 100;
        }

        return $rows;
    }

    /**
     * @param  list<array{from: int|string, teacher: string, head: string}>  $bands
     * @return list<array>
     *
     * @throws ExamException when the bands leave a gap at the bottom or repeat a start
     */
    public function saveBands(array $bands): array
    {
        $clean = collect($bands)->map(fn ($b) => [
            'from' => (int) $b['from'], 'teacher' => trim((string) $b['teacher']), 'head' => trim((string) $b['head']),
        ])->sortBy('from')->values();

        if ($clean->isEmpty() || $clean->first()['from'] !== 0 || $clean->pluck('from')->unique()->count() !== $clean->count()
            || $clean->contains(fn ($b) => $b['from'] < 0 || $b['from'] > 99 || $b['teacher'] === '' || $b['head'] === '')) {
            throw ExamException::invalid(__('exams.err.bands_invalid'), 'BANDS_INVALID');
        }

        $school = Tenant::school();
        $settings = $school->settings ?? [];
        $settings['exams']['comment_bands'] = $clean->all();
        $school->update(['settings' => $settings]);

        return $this->bands();
    }

    private function bandFor(array $bands, float $average): array
    {
        $found = $bands[0];
        foreach ($bands as $band) {
            if ($band['from'] <= $average) {
                $found = $band;
            }
        }

        return $found;
    }

    // ── group comments ───────────────────────────────────────────────────────

    /**
     * Fill comments from the bands, for one class or the whole exam.
     *
     * @param  'teacher'|'head'  $role
     * @return array{applied: int, kept: int, no_marks: int, by_band: array<int, int>}
     */
    public function apply(Exam $exam, ?SchoolClass $class, string $role, bool $overwrite, ?User $actor): array
    {
        if ($exam->isPublished()) {
            throw ExamException::state(__('exams.state.results_published'), 'EXAM_PUBLISHED');
        }

        $bands = $this->bands();
        $field = $role === 'head' ? 'head_comment' : 'teacher_comment';
        $out = ['applied' => 0, 'kept' => 0, 'no_marks' => 0, 'by_band' => array_fill_keys(array_column($bands, 'from'), 0)];

        $classes = $class ? collect([$class]) : ExamScope::classesFor($exam)->get();
        foreach ($classes as $c) {
            ExamScope::assertCovers($exam, $c);
            foreach ($this->results->classResults($exam, $c)['rows'] as $row) {
                if ($row['average'] === null) {
                    $out['no_marks']++;

                    continue;
                }
                $comment = ExamComment::firstOrNew(['exam_id' => $exam->id, 'student_id' => $row['student_id']]);
                if (! $overwrite && filled($comment->{$field})) {
                    $out['kept']++;

                    continue;
                }
                $band = $this->bandFor($bands, (float) $row['average']);
                $comment->{$field} = $band[$role === 'head' ? 'head' : 'teacher'];
                $comment->entered_by = $actor?->id;
                $comment->save();
                $out['applied']++;
                $out['by_band'][$band['from']]++;
            }
        }

        return $out;
    }

    // ── progress and sign-off ────────────────────────────────────────────────

    /**
     * Where each class stands: pupils with marks, comments still missing, and the two sign-offs.
     *
     * @return array{required: bool, complete: bool, classes: list<array>}
     */
    public function status(Exam $exam): array
    {
        $signoffs = ExamSignoff::where('exam_id', $exam->id)->get()->groupBy('school_class_id');
        $classes = [];

        foreach (ExamScope::classesFor($exam)->orderBy('school_classes.name')->get() as $class) {
            $enrolled = $this->results->enrolled($class)->pluck('student_id');
            $marked = $enrolled->isEmpty() ? collect() : ExamResult::where('exam_id', $exam->id)->whereIn('student_id', $enrolled)->whereNotNull('marks')->pluck('student_id')->unique();
            if ($marked->isEmpty()) {
                continue;
            }
            $comments = ExamComment::where('exam_id', $exam->id)->whereIn('student_id', $marked)->get()->keyBy('student_id');
            $mine = $signoffs->get($class->id, collect())->keyBy('stage');

            $classes[] = [
                'id' => $class->id,
                'label' => ExamScope::classLabel($class),
                'pupils' => $marked->count(),
                'teacher_missing' => $marked->filter(fn ($id) => blank($comments->get($id)?->teacher_comment))->count(),
                'head_missing' => $marked->filter(fn ($id) => blank($comments->get($id)?->head_comment))->count(),
                'teacher_signed' => $this->signoffInfo($mine->get(ExamSignoff::TEACHER)),
                'head_signed' => $this->signoffInfo($mine->get(ExamSignoff::HEAD)),
            ];
        }

        return [
            'required' => (bool) $exam->require_comments,
            'complete' => collect($classes)->every(fn ($c) => $c['teacher_signed'] && $c['head_signed']),
            'classes' => $classes,
        ];
    }

    private function signoffInfo(?ExamSignoff $s): ?array
    {
        return $s ? ['by' => $s->signed_by_name, 'at' => $s->signed_at?->toIso8601String()] : null;
    }

    /** The class teacher submits their comments, or the head approves them, for one class. */
    public function submit(Exam $exam, SchoolClass $class, string $stage, ?User $actor): ExamSignoff
    {
        if (! $exam->isOpen()) {
            throw $exam->isPublished()
                ? ExamException::state(__('exams.state.results_published'), 'EXAM_PUBLISHED')
                : ExamException::state(__('exams.state.not_open'), 'EXAM_NOT_OPEN');
        }
        ExamScope::assertCovers($exam, $class);

        $row = collect($this->status($exam)['classes'])->firstWhere('id', $class->id);
        if (! $row) {
            throw ExamException::invalid(__('exams.err.comments_no_pupils'), 'COMMENTS_NO_PUPILS');
        }
        if ($stage === ExamSignoff::HEAD && ! $row['teacher_signed']) {
            throw ExamException::state(__('exams.err.comments_teacher_first'), 'TEACHER_STAGE_FIRST');
        }
        $missing = $stage === ExamSignoff::HEAD ? $row['head_missing'] : $row['teacher_missing'];
        if ($missing > 0) {
            throw ExamException::invalid(__('exams.err.comments_missing', ['count' => $missing]), 'COMMENTS_MISSING', ['missing' => $missing]);
        }

        return ExamSignoff::updateOrCreate(
            ['exam_id' => $exam->id, 'school_class_id' => $class->id, 'stage' => $stage],
            ['signed_by' => $actor?->id, 'signed_by_name' => $actor?->name, 'signed_at' => now()],
        );
    }

    /** Take a sign-off back; reopening the class teacher's also takes back the head's. */
    public function reopen(Exam $exam, SchoolClass $class, string $stage): void
    {
        if ($exam->isPublished()) {
            throw ExamException::state(__('exams.state.results_published'), 'EXAM_PUBLISHED');
        }
        $stages = $stage === ExamSignoff::TEACHER ? [ExamSignoff::TEACHER, ExamSignoff::HEAD] : [ExamSignoff::HEAD];
        if (! ExamSignoff::where(['exam_id' => $exam->id, 'school_class_id' => $class->id, 'stage' => $stage])->exists()) {
            throw ExamException::state(__('exams.err.comments_not_signed'), 'NOT_SIGNED');
        }
        ExamSignoff::where(['exam_id' => $exam->id, 'school_class_id' => $class->id])->whereIn('stage', $stages)->delete();
    }

    public function isSigned(Exam $exam, SchoolClass|int $class, string $stage): bool
    {
        return ExamSignoff::where(['exam_id' => $exam->id, 'school_class_id' => $class instanceof SchoolClass ? $class->id : $class, 'stage' => $stage])->exists();
    }

    /** @throws ExamException when the exam asks for comments and a class has not finished them */
    public function assertReadyToPublish(Exam $exam): void
    {
        if (! $exam->require_comments) {
            return;
        }
        $pending = collect($this->status($exam)['classes'])
            ->filter(fn ($c) => ! $c['teacher_signed'] || ! $c['head_signed'])
            ->map(fn ($c) => ['class_id' => $c['id'], 'class' => $c['label'], 'waiting_for' => $c['teacher_signed'] ? 'head' : 'teacher']);

        if ($pending->isNotEmpty()) {
            throw ExamException::invalid(
                __('exams.err.comments_pending', ['classes' => $pending->pluck('class')->join(', ', ' & ')]),
                'COMMENTS_PENDING',
                ['classes' => $pending->values()->all()],
            );
        }
    }
}
