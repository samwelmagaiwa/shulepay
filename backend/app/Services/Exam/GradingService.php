<?php

namespace App\Services\Exam;

use App\Enums\SchoolLevel;
use App\Models\Exam;
use App\Models\GradingScale;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A school's marks-to-grade scale.
 *
 * Each band stores only its lower bound; a mark takes the band with the highest
 * lower bound that does not exceed it. That makes overlaps and gaps impossible
 * by construction, and the one invariant left to enforce is that a band starts
 * at 0 so every mark 0-100 has a grade.
 */
class GradingService
{
    private ?Collection $bands = null;

    /** Bands ordered best-first. Seeds the level-appropriate defaults on first use. */
    public function bands(): Collection
    {
        if ($this->bands === null) {
            $this->ensureDefaults();
            $this->bands = GradingScale::query()->orderByDesc('min_marks')->get();
        }

        return $this->bands;
    }

    /**
     * The grader to use for a given exam: a published exam grades against the
     * scale frozen when it was published, every other exam against the live one.
     */
    public function forExam(Exam $exam): static
    {
        if (! $exam->isPublished() || empty($exam->grading_snapshot)) {
            return $this;
        }

        $frozen = clone $this;
        $frozen->bands = collect($exam->grading_snapshot)
            ->map(fn (array $band) => new GradingScale($band))
            ->sortByDesc('min_marks')
            ->values();

        return $frozen;
    }

    /**
     * Bands as readable ranges, best first: A is 75-100, B is 65-74.99 and so on.
     *
     * @return array<int, array{grade: string, from: float, to: float, points: int|null, remark: string|null}>
     */
    public function ranges(): array
    {
        $upper = 100.0;
        $ranges = [];

        foreach ($this->bands() as $band) {
            $ranges[] = [
                'grade' => $band->grade,
                'from' => $band->min_marks,
                'to' => $upper,
                'points' => $band->points,
                'remark' => $band->remark,
            ];
            $upper = round($band->min_marks - 0.01, 2);
        }

        return $ranges;
    }

    /** The current scale in the shape stored on an exam at publish time. */
    public function snapshot(): array
    {
        return $this->bands()
            ->map(fn (GradingScale $band) => $band->only(['grade', 'min_marks', 'points', 'remark']))
            ->values()
            ->all();
    }

    public function ensureDefaults(): void
    {
        if (GradingScale::query()->exists()) {
            return;
        }

        foreach (self::defaultBands(ExamScope::school()) as $band) {
            GradingScale::create($band);
        }
    }

    public function gradeFor(?float $marks): ?GradingScale
    {
        if ($marks === null) {
            return null;
        }

        $marks = round($marks, 2);

        return $this->bands()->first(fn (GradingScale $band) => $band->min_marks <= $marks);
    }

    public function remarkFor(?string $grade): ?string
    {
        return $grade === null ? null : $this->bands()->firstWhere('grade', $grade)?->remark;
    }

    /**
     * Replace the whole scale in one transaction.
     *
     * @param  array<int, array{grade: string, min_marks: float|int|string, points?: int|null, remark?: string|null}>  $bands
     */
    public function replaceBands(array $bands): Collection
    {
        DB::transaction(function () use ($bands) {
            // Unique indexes on grade and min_marks forbid inserting the new
            // scale beside the old one, so the old bands go first.
            GradingScale::query()->delete();

            foreach ($bands as $band) {
                GradingScale::create([
                    'grade' => strtoupper(trim($band['grade'])),
                    'min_marks' => $band['min_marks'],
                    'points' => $band['points'] ?? null,
                    'remark' => $band['remark'] ?? null,
                ]);
            }
        });

        $this->bands = null;

        return $this->bands();
    }

    /**
     * Tanzanian national scales: PSLE for primary, CSEE for secondary.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultBands(?School $school): array
    {
        // School::level is cast to an enum with Swahili aliases (Msingi = primary).
        if (in_array($school?->level, [SchoolLevel::Primary, SchoolLevel::Msingi], true)) {
            return [
                ['grade' => 'A', 'min_marks' => 81, 'points' => 1, 'remark' => 'Excellent'],
                ['grade' => 'B', 'min_marks' => 61, 'points' => 2, 'remark' => 'Very Good'],
                ['grade' => 'C', 'min_marks' => 41, 'points' => 3, 'remark' => 'Good'],
                ['grade' => 'D', 'min_marks' => 21, 'points' => 4, 'remark' => 'Satisfactory'],
                ['grade' => 'E', 'min_marks' => 0, 'points' => 5, 'remark' => 'Fail'],
            ];
        }

        return [
            ['grade' => 'A', 'min_marks' => 75, 'points' => 1, 'remark' => 'Excellent'],
            ['grade' => 'B', 'min_marks' => 65, 'points' => 2, 'remark' => 'Very Good'],
            ['grade' => 'C', 'min_marks' => 45, 'points' => 3, 'remark' => 'Good'],
            ['grade' => 'D', 'min_marks' => 30, 'points' => 4, 'remark' => 'Satisfactory'],
            ['grade' => 'F', 'min_marks' => 0, 'points' => 5, 'remark' => 'Fail'],
        ];
    }
}
