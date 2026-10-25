<?php

namespace App\Services\Exam;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSubmission;
use App\Models\SchoolNotification;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who still owes marks: a class-by-subject grid for one exam showing how many marks are in, whether
 * the teacher has submitted, and who teaches it. Also sends teachers a reminder about what is missing.
 */
class ExamProgressService
{
    public function __construct(private readonly ExamResultsService $results) {}

    /** @return array{classes: array<int, array>, entered: int, total: int, deadline: ?string, deadline_passed: bool} */
    public function grid(Exam $exam): array
    {
        $submitted = ExamSubmission::where('exam_id', $exam->id)->get()
            ->mapWithKeys(fn ($s) => [$s->school_class_id.'-'.$s->subject_id => true]);
        $teachers = $this->teacherNames();

        $classes = [];
        $entered = $total = 0;

        foreach (ExamScope::classesFor($exam)->orderBy('school_classes.name')->get() as $class) {
            $subjects = $this->results->subjectsFor($class);
            $studentIds = $this->results->enrolled($class)->pluck('student_id');

            $counts = $subjects->isEmpty() || $studentIds->isEmpty() ? collect() : ExamResult::query()
                ->where('exam_id', $exam->id)
                ->whereIn('student_id', $studentIds)
                ->whereIn('subject_id', $subjects->pluck('id'))
                ->where(fn ($q) => $q->whereNotNull('marks')->orWhere('is_absent', true))
                ->selectRaw('subject_id, count(*) as c')->groupBy('subject_id')->pluck('c', 'subject_id');

            $rows = $subjects->map(function ($subject) use ($class, $studentIds, $counts, $submitted, $teachers) {
                $in = (int) ($counts[$subject->id] ?? 0);

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'entered' => $in,
                    'total' => $studentIds->count(),
                    'complete' => $studentIds->count() > 0 && $in >= $studentIds->count(),
                    'submitted' => isset($submitted[$class->id.'-'.$subject->id]),
                    'teachers' => $teachers[$class->id.'-'.$subject->id] ?? [],
                ];
            })->values();

            $classEntered = (int) $rows->sum('entered');
            $classTotal = (int) $rows->sum('total');
            $entered += $classEntered;
            $total += $classTotal;

            $classes[] = [
                'id' => $class->id,
                'label' => ExamScope::classLabel($class),
                'students' => $studentIds->count(),
                'entered' => $classEntered,
                'total' => $classTotal,
                'subjects' => $rows->all(),
            ];
        }

        return [
            'classes' => $classes,
            'entered' => $entered,
            'total' => $total,
            'deadline' => $exam->marks_deadline?->toDateString(),
            'deadline_passed' => $exam->deadlinePassed(),
        ];
    }

    /**
     * Notify the teachers whose marks are still missing. Notifications go by role, so each classroom-teacher
     * role gets one notice that names every teacher and what they still owe.
     *
     * @return int how many teachers are named
     */
    public function remind(Exam $exam): int
    {
        $byTeacher = [];
        foreach ($this->grid($exam)['classes'] as $class) {
            foreach ($class['subjects'] as $subject) {
                if ($subject['complete'] || $subject['submitted'] || $subject['total'] === 0) {
                    continue;
                }
                foreach ($subject['teachers'] as $teacher) {
                    $byTeacher[$teacher['id']]['name'] = $teacher['name'];
                    $byTeacher[$teacher['id']]['role'] = $teacher['role'];
                    $byTeacher[$teacher['id']]['items'][] = $subject['name'].' ('.$class['label'].')';
                }
            }
        }

        $byRole = collect($byTeacher)->groupBy('role');
        foreach ($byRole as $role => $teachers) {
            $list = $teachers->map(fn ($t) => $t['name'].': '.implode(', ', $t['items']))->implode('; ');
            SchoolNotification::create([
                'school_id' => ExamScope::school()?->id,
                'type' => 'exam_marks_reminder',
                'title' => __('exams.remind.title', ['exam' => $exam->name]),
                'body' => __('exams.remind.body', ['exam' => $exam->name, 'list' => $list]),
                'data' => ['exam_id' => $exam->id],
                'recipient_role' => $role ?: 'all',
                'is_read' => false,
            ]);
        }

        return count($byTeacher);
    }

    /** @return array<string, array<int, array{id: int, name: string, role: ?string}>> keyed "classId-subjectId" */
    private function teacherNames(): array
    {
        $assignments = TeacherAssignment::all();
        $users = User::with('roles')->whereIn('id', $assignments->pluck('user_id')->unique())->get()->keyBy('id');

        return $assignments->groupBy(fn ($a) => $a->school_class_id.'-'.$a->subject_id)
            ->map(fn (Collection $group) => $group->map(fn ($a) => $users->get($a->user_id))->filter()
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->roles->first()?->name])->values()->all())
            ->all();
    }
}
