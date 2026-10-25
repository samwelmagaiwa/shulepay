<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Attendance over a day or a period: by day, by class, who was away, and each pupil's rate.
 * A late pupil counts as attending, so the rate is (present + late) / records.
 * Every read is limited to the schools given — attendance rows are not scoped automatically.
 */
class AttendanceHistoryService
{
    /** Longest period one request may cover. */
    public const MAX_DAYS = 93;

    /** Below this rate a pupil is flagged. */
    public const LOW_RATE = 80;

    /** @param  list<int>  $schoolIds  empty = every school */
    public function __construct(private array $schoolIds = []) {}

    /**
     * Turn the asked-for dates into a usable period: defaults to today, swapped if reversed,
     * and cut to MAX_DAYS ending at the later date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function clampRange(?string $from, ?string $to): array
    {
        $a = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $b = $to ? Carbon::parse($to)->startOfDay() : $a->copy();
        if ($a->gt($b)) {
            [$a, $b] = [$b, $a];
        }
        if ($a->diffInDays($b) > self::MAX_DAYS - 1) {
            $a = $b->copy()->subDays(self::MAX_DAYS - 1);
        }

        return [$a, $b];
    }

    private function records(CarbonInterface $from, CarbonInterface $to, ?int $classId)
    {
        return Attendance::query()
            ->when($this->schoolIds, fn ($q) => $q->whereIn('school_id', $this->schoolIds))
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /** @return Collection<int, SchoolClass> */
    public function classes(?int $classId = null): Collection
    {
        return SchoolClass::query()
            ->when($this->schoolIds, fn ($q) => $q->whereIn('school_id', $this->schoolIds))
            ->when($classId, fn ($q) => $q->where('id', $classId))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** fullName() leaves a double space when there is no middle name. */
    private static function name(?Student $s): string
    {
        return $s ? preg_replace('/\s+/', ' ', $s->fullName()) : '—';
    }

    private static function rate(int $present, int $late, int $total): ?int
    {
        return $total > 0 ? (int) round((($present + $late) / $total) * 100) : null;
    }

    /** Weekdays in the period that have already happened — the days a register was due. */
    private static function schoolDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        $last = $to->copy()->min(now()->startOfDay());
        for ($d = $from->copy(); $d->lte($last); $d->addDay()) {
            if ($d->isWeekday()) {
                $days[] = $d->toDateString();
            }
        }

        return $days;
    }

    public function overview(CarbonInterface $from, CarbonInterface $to, ?int $classId = null): array
    {
        $rows = $this->records($from, $to, $classId)
            ->selectRaw('date, school_class_id, status, COUNT(*) as n')
            ->groupBy('date', 'school_class_id', 'status')->get()
            ->each(fn ($r) => $r->date = Carbon::parse($r->date)->toDateString());

        $count = fn (Collection $rs, string $s) => (int) $rs->where('status', $s)->sum('n');
        $bucket = function (Collection $rs) use ($count) {
            $p = $count($rs, 'present');
            $l = $count($rs, 'late');
            $a = $count($rs, 'absent');

            return ['present' => $p, 'late' => $l, 'absent' => $a, 'total' => $p + $l + $a, 'rate' => self::rate($p, $l, $p + $l + $a)];
        };

        $t = $bucket($rows);
        $schoolDays = self::schoolDays($from, $to);

        $days = $rows->groupBy('date')->map(fn ($rs, $date) => ['date' => $date] + $bucket($rs))
            ->sortByDesc('date')->values()->all();

        $classes = $this->classes($classId)->map(function (SchoolClass $c) use ($rows, $bucket, $schoolDays) {
            $rs = $rows->where('school_class_id', $c->id);
            $marked = $rs->pluck('date')->unique();
            $b = $bucket($rs);

            return [
                'id' => $c->id,
                'label' => $c->name,
                'present' => $b['present'], 'late' => $b['late'], 'absent' => $b['absent'], 'rate' => $b['rate'],
                'days_marked' => $marked->count(),
                'days_missing' => count(array_diff($schoolDays, $marked->all())),
            ];
        })->values()->all();

        // One school day: say which classes did not take the register.
        $notMarked = [];
        if ($from->isSameDay($to) && count($schoolDays) === 1) {
            $done = $rows->pluck('school_class_id')->unique();
            $notMarked = $this->classes($classId)->reject(fn ($c) => $done->contains($c->id))->pluck('name')->values()->all();
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => ['present' => $t['present'], 'late' => $t['late'], 'absent' => $t['absent'], 'records' => $t['total'], 'rate' => $t['rate']],
            'days' => $days,
            'classes' => $classes,
            'not_marked' => $notMarked,
            'low_rate' => self::LOW_RATE,
        ];
    }

    /** Pupils marked absent or late in the period, absent first. */
    public function absentees(CarbonInterface $from, CarbonInterface $to, ?int $classId = null): array
    {
        $rows = $this->records($from, $to, $classId)->whereIn('status', ['absent', 'late'])
            ->with(['student', 'schoolClass'])->get();

        return $rows->map(fn ($r) => [
            'student_id' => $r->student_id,
            'name' => self::name($r->student),
            'class' => $r->schoolClass?->name,
            'date' => Carbon::parse($r->date)->toDateString(),
            'status' => $r->status,
            'remarks' => $r->remarks,
        ])->sortBy([['status', 'asc'], ['name', 'asc'], ['date', 'desc']])->values()->all();
    }

    /** Every pupil with a record in the period, lowest rate first (ties alphabetically). */
    public function pupils(CarbonInterface $from, CarbonInterface $to, ?int $classId = null): array
    {
        $rows = $this->records($from, $to, $classId)
            ->selectRaw('student_id, school_class_id, status, COUNT(*) as n')
            ->groupBy('student_id', 'school_class_id', 'status')->get()->groupBy('student_id');

        $students = Student::whereIn('id', $rows->keys())->get()->keyBy('id');
        $classes = SchoolClass::whereIn('id', $rows->flatten()->pluck('school_class_id')->unique())->pluck('name', 'id');

        $pupils = $rows->map(function ($rs, $id) use ($students, $classes) {
            $n = fn (string $s) => (int) $rs->where('status', $s)->sum('n');
            [$p, $l, $a] = [$n('present'), $n('late'), $n('absent')];
            $rate = self::rate($p, $l, $p + $l + $a);

            return [
                'id' => (int) $id,
                'name' => self::name($students[$id] ?? null),
                'class' => $classes[$rs->first()->school_class_id] ?? null,
                'days' => $p + $l + $a, 'present' => $p, 'late' => $l, 'absent' => $a,
                'rate' => $rate,
                'low' => $rate !== null && $rate < self::LOW_RATE,
            ];
        })->sortBy([['rate', 'asc'], ['name', 'asc']])->values()->all();

        return $pupils;
    }
}
