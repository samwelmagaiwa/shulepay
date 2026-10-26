<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Exam\ExamScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance over time: what the registers say for a day or a period, class by class and pupil by pupil.
 *
 * A pupil who arrived late counts as attending (the same rule the exam report card uses), so
 * rate = (present + late) / recorded days. "Not marked" means a school day with no register for a class.
 * Used by the History page, the printouts and the AI assistant, so all of them agree.
 */
class AttendanceHistoryService
{
    /** A pupil below this attendance rate is flagged. */
    public const LOW_RATE = 80;

    /** The longest period one request may cover. */
    public const MAX_DAYS = 366;

    /** @return Collection<int, SchoolClass> the classes in scope (one, or the whole school) */
    private function classes(?int $classId): Collection
    {
        return ExamScope::classes()->when($classId, fn ($q) => $q->where('school_classes.id', $classId))->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function clampRange(?string $from, ?string $to): array
    {
        $end = $to ? Carbon::parse($to)->startOfDay() : now()->startOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : $end->copy();
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS - 1);
        }

        return [$start, $end];
    }

    private function rate(int $present, int $late, int $total): ?int
    {
        return $total > 0 ? (int) round(($present + $late) / $total * 100) : null;
    }

    /** School days (Monday to Friday) between two dates; no public holidays are known. */
    public function schoolDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days += $d->isWeekend() ? 0 : 1;
        }

        return $days;
    }

    /**
     * The summary of a period: totals, one row per date, one row per class, and what was not marked.
     *
     * @return array<string, mixed>
     */
    public function overview(Carbon $from, Carbon $to, ?int $classId = null): array
    {
        $classes = $this->classes($classId);
        $ids = $classes->pluck('id');

        $rows = Attendance::query()
            ->whereIn('school_class_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("date, school_class_id,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent")
            ->groupBy('date', 'school_class_id')
            ->get();

        $present = (int) $rows->sum('present');
        $late = (int) $rows->sum('late');
        $absent = (int) $rows->sum('absent');
        $total = $present + $late + $absent;

        $days = $rows->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString())->map(function (Collection $day, string $date) use ($classes) {
            $p = (int) $day->sum('present');
            $l = (int) $day->sum('late');
            $a = (int) $day->sum('absent');

            return [
                'date' => $date,
                'weekday' => Carbon::parse($date)->translatedFormat('D'),
                'classes_marked' => $day->count(),
                'classes_total' => $classes->count(),
                'present' => $p,
                'late' => $l,
                'absent' => $a,
                'total' => $p + $l + $a,
                'rate' => $this->rate($p, $l, $p + $l + $a),
            ];
        })->sortByDesc('date')->values();

        $schoolDays = $this->schoolDays($from, $to->copy()->min(now()->startOfDay()));
        $byClass = $classes->map(function (SchoolClass $c) use ($rows, $schoolDays) {
            $mine = $rows->where('school_class_id', $c->id);
            $p = (int) $mine->sum('present');
            $l = (int) $mine->sum('late');
            $a = (int) $mine->sum('absent');
            $marked = $mine->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->count();

            return [
                'id' => $c->id,
                'label' => ExamScope::classLabel($c),
                'days_marked' => $marked,
                'days_missing' => max(0, $schoolDays - $marked),
                'present' => $p,
                'late' => $l,
                'absent' => $a,
                'rate' => $this->rate($p, $l, $p + $l + $a),
            ];
        })->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'school_days' => $schoolDays,
            'totals' => ['present' => $present, 'late' => $late, 'absent' => $absent, 'records' => $total, 'rate' => $this->rate($present, $late, $total)],
            'days' => $days->all(),
            'classes' => $byClass->all(),
            // for a single day: the classes whose register was not taken
            'not_marked' => $from->isSameDay($to) && ! $from->isWeekend() && $from->lte(now())
                ? $byClass->filter(fn ($c) => $c['days_marked'] === 0)->pluck('label')->values()->all()
                : [],
            'low_rate' => self::LOW_RATE,
        ];
    }

    /**
     * Who was away: pupils marked absent (and late) on one day, by class.
     *
     * @return list<array{student_id: int, name: string, class: string, status: string, remarks: ?string}>
     */
    public function absentees(Carbon $date, ?int $classId = null, bool $includeLate = true): array
    {
        $classes = $this->classes($classId)->keyBy('id');

        return Attendance::query()
            ->with('student')
            ->whereIn('school_class_id', $classes->keys())
            ->whereDate('date', $date->toDateString())
            ->whereIn('status', $includeLate ? ['absent', 'late'] : ['absent'])
            ->orderBy('school_class_id')->orderBy('status')
            ->get()
            ->filter(fn ($a) => $a->student !== null)
            ->map(fn ($a) => [
                'student_id' => $a->student_id,
                'name' => ExamScope::personName($a->student),
                'class' => ExamScope::classLabel($classes[$a->school_class_id]),
                'status' => $a->status,
                'remarks' => $a->remarks,
            ])->sortBy([['class', 'asc'], ['status', 'asc'], ['name', 'asc']])->values()->all();
    }

    /**
     * Each pupil's attendance over a period, weakest first.
     *
     * @return array{from: string, to: string, low_rate: int, pupils: list<array>}
     */
    public function pupils(Carbon $from, Carbon $to, ?int $classId = null, ?int $limit = null): array
    {
        $classes = $this->classes($classId)->keyBy('id');

        $rows = Attendance::query()
            ->whereIn('school_class_id', $classes->keys())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("student_id, school_class_id,
                COUNT(*) AS days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent")
            ->groupBy('student_id', 'school_class_id')
            ->get();

        $students = Student::whereIn('id', $rows->pluck('student_id')->unique())->get()->keyBy('id');

        $pupils = $rows->filter(fn ($r) => $students->has($r->student_id))->map(function ($r) use ($students, $classes) {
            $rate = $this->rate((int) $r->present, (int) $r->late, (int) $r->days);

            return [
                'student_id' => (int) $r->student_id,
                'name' => ExamScope::personName($students[$r->student_id]),
                'class' => ExamScope::classLabel($classes[$r->school_class_id]),
                'days' => (int) $r->days,
                'present' => (int) $r->present,
                'late' => (int) $r->late,
                'absent' => (int) $r->absent,
                'rate' => $rate,
                'low' => $rate !== null && $rate < self::LOW_RATE,
            ];
        })->sortBy([['rate', 'asc'], ['absent', 'desc'], ['name', 'asc']])->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'low_rate' => self::LOW_RATE,
            'pupils' => ($limit ? $pupils->take($limit) : $pupils)->values()->all(),
        ];
    }
}
