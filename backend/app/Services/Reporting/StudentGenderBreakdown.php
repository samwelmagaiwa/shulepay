<?php

namespace App\Services\Reporting;

use App\Models\Student;

/**
 * Headcount of enrolled students by gender.
 *
 * The population is deliberately identical to the dashboard's "All Students"
 * figure — an active enrollment in the school, on a student who has not been
 * deleted — so the slices of the gender chart always add up to that card. A
 * chart and a card built from different populations would disagree, and the
 * reader would have no way to tell which one is right.
 *
 * Each student is counted once, however many active enrollments they hold,
 * because the grouping runs over students rather than enrollment rows.
 */
class StudentGenderBreakdown
{
    /**
     * Stored spellings mapped to the three buckets. 'me' / 'ke' are the Swahili
     * codes the column held before it was migrated to male / female; they are
     * still accepted so any row the migration missed is counted, not dropped.
     */
    private const MAP = [
        'male' => 'male',
        'me' => 'male',
        'female' => 'female',
        'ke' => 'female',
    ];

    /**
     * @return array{male: int, female: int, unspecified: int, total: int}
     */
    public function for(?int $schoolId): array
    {
        $rows = Student::query()
            ->whereHas('enrollments', fn ($q) => $q
                ->withoutGlobalScope('school')
                ->where('status', 'active')
                ->when($schoolId, fn ($e) => $e->where('school_id', $schoolId))
            )
            ->selectRaw('LOWER(TRIM(gender)) AS g, COUNT(*) AS n')
            ->groupByRaw('LOWER(TRIM(gender))')
            ->pluck('n', 'g');

        $result = ['male' => 0, 'female' => 0, 'unspecified' => 0];

        foreach ($rows as $gender => $count) {
            // NULL, blank, or anything unrecognised is reported as unspecified
            // rather than silently discarded, so the total stays exact.
            $bucket = self::MAP[(string) $gender] ?? 'unspecified';
            $result[$bucket] += (int) $count;
        }

        $result['total'] = $result['male'] + $result['female'] + $result['unspecified'];

        return $result;
    }
}
