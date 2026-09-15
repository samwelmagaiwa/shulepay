<?php

namespace App\Services\Reporting;

use App\Models\Enrollment;
use App\Models\SchoolClass;

/**
 * Enrolled students per class, in the school's own class order.
 *
 * Replaces a frontend step that guessed which "age group" a class belonged to
 * by matching its name against spellings it knew (form1, kidato1, darasa1…).
 * Secondary classes are named FORM ONE…FORM FOUR, which normalise to formone…
 * and matched nothing, so the chart reported no data for a school full of
 * students. Returning the real classes by name removes the guessing entirely:
 * a class is shown because it exists, not because its spelling was anticipated.
 *
 * The population matches the All Students card — an active enrollment on a
 * student who has not been deleted — so the bars add up to that figure.
 */
class StudentClassDistribution
{
    /**
     * @return list<array{class_id: int, class_name: string, students: int}>
     */
    public function for(?int $schoolId): array
    {
        $counts = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereHas('student')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('school_class_id, COUNT(DISTINCT student_id) AS students')
            ->groupBy('school_class_id')
            ->pluck('students', 'school_class_id');

        if ($counts->isEmpty()) {
            return [];
        }

        // Ordered by the stored sort_order, then id. Secondary classes all carry
        // sort_order 999, and name order would put FORM FOUR before FORM ONE;
        // id preserves the order they were created in, which is ONE..FOUR.
        return SchoolClass::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $class) => [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'students' => (int) $counts[$class->id],
            ])
            ->values()
            ->all();
    }
}
