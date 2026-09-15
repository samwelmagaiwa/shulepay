<?php

namespace App\Services\Reporting;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Fees collected per class, with student counts, in the school's class order.
 *
 * Each payment is attributed to the class its student was enrolled in FOR THE
 * YEAR THE INVOICE BELONGS TO, matched on student + school + academic year.
 * That triple is unique on enrollments, so every payment lands in exactly one
 * class. The previous query joined any active enrollment of the student, which
 * counted a payment twice for a student active in two years and dropped it
 * entirely once the student was no longer active.
 *
 * A payment whose invoice has no matching enrollment is reported as unassigned
 * rather than discarded, so the class figures plus unassigned always equal the
 * school's total collected. Classes are returned by their real names; nothing
 * on the page has to recognise how a class is spelled.
 */
class ClassFeeCollection
{
    /**
     * @return array{
     *     classes: list<array{class_id: int, class_name: string, students: int, collected_cents: int}>,
     *     unassigned_cents: int,
     *     total_cents: int
     * }
     */
    public function for(?int $schoolId): array
    {
        $paymentTable = (new Payment)->getTable();

        // One grouped query: collected per enrollment class, NULL where no
        // enrollment matches the invoice's student, school and year.
        $collected = Payment::allSchools()
            ->join('invoices', 'invoices.id', '=', "{$paymentTable}.invoice_id")
            ->leftJoin('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'invoices.student_id')
                    ->on('enrollments.school_id', '=', 'invoices.school_id')
                    ->on('enrollments.academic_year_id', '=', 'invoices.academic_year_id');
            })
            ->when($schoolId, fn ($q) => $q->where("{$paymentTable}.school_id", $schoolId))
            ->groupBy('enrollments.school_class_id')
            ->select('enrollments.school_class_id', DB::raw("SUM({$paymentTable}.amount_cents) AS collected"))
            ->get();

        $unassigned = (int) $collected->whereNull('school_class_id')->sum('collected');
        $byClass = $collected->whereNotNull('school_class_id')
            ->mapWithKeys(fn ($r) => [(int) $r->school_class_id => (int) $r->collected]);

        // Same population as the All Students card.
        $students = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereHas('student')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->selectRaw('school_class_id, COUNT(DISTINCT student_id) AS n')
            ->groupBy('school_class_id')
            ->pluck('n', 'school_class_id');

        // A class appears if it has students now or collected money at any point,
        // so a class that emptied out still accounts for what it brought in.
        $classIds = $byClass->keys()->merge($students->keys())->unique()->values();

        $classes = $classIds->isEmpty() ? collect() : SchoolClass::query()
            ->whereIn('id', $classIds)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            // Tied sort_order (secondary classes all carry 999) falls back to
            // creation order rather than name, which would put FORM FOUR first.
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (SchoolClass $c) => [
                'class_id' => $c->id,
                'class_name' => $c->name,
                'students' => (int) ($students[$c->id] ?? 0),
                'collected_cents' => (int) ($byClass[$c->id] ?? 0),
            ])
            ->values();

        return [
            'classes' => $classes->all(),
            'unassigned_cents' => $unassigned,
            'total_cents' => (int) $classes->sum('collected_cents') + $unassigned,
        ];
    }

    /**
     * The payments behind unassigned_cents, grouped by student.
     *
     * Uses the identical join as for() - student + school + academic year - so
     * the grand total here always equals the unassigned figure on the dashboard.
     * Each student carries the years they ARE enrolled in, because the usual cause
     * is a missing or wrong-year enrollment, and seeing "billed for 2026, enrolled
     * only in 2027" points straight at the fix.
     *
     * Read only.
     *
     * @return array{students: list<array<string, mixed>>, total_cents: int}
     */
    public function unassigned(?int $schoolId): array
    {
        $paymentTable = (new Payment)->getTable();

        $payments = Payment::allSchools()
            ->join('invoices', 'invoices.id', '=', "{$paymentTable}.invoice_id")
            ->leftJoin('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'invoices.student_id')
                    ->on('enrollments.school_id', '=', 'invoices.school_id')
                    ->on('enrollments.academic_year_id', '=', 'invoices.academic_year_id');
            })
            ->whereNull('enrollments.id')
            ->when($schoolId, fn ($q) => $q->where("{$paymentTable}.school_id", $schoolId))
            ->select([
                "{$paymentTable}.id", "{$paymentTable}.amount_cents", "{$paymentTable}.paid_at",
                'invoices.student_id', 'invoices.invoice_number', 'invoices.academic_year_id',
                'invoices.school_id AS invoice_school_id',
            ])
            ->orderBy('invoices.student_id')
            ->orderBy("{$paymentTable}.paid_at")
            ->get();

        if ($payments->isEmpty()) {
            return ['students' => [], 'total_cents' => 0];
        }

        $studentIds = $payments->pluck('student_id')->unique()->values();

        // withTrashed: a deleted student's payments still count, and the row is
        // only identifiable by name if the name can still be read.
        $students = Student::withTrashed()->whereIn('id', $studentIds)->get()->keyBy('id');

        $yearNames = AcademicYear::query()
            ->whereIn('id', $payments->pluck('academic_year_id')->unique())
            ->pluck('name', 'id');

        $enrollments = Enrollment::withoutGlobalScope('school')
            ->whereIn('student_id', $studentIds)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->with(['academicYear:id,name', 'schoolClass:id,name'])
            ->get()
            ->groupBy('student_id');

        $rows = $payments->groupBy('student_id')->map(function ($group, $studentId) use ($students, $yearNames, $enrollments) {
            $student = $students->get($studentId);

            return [
                'student_id' => (int) $studentId,
                'student_name' => $student?->fullName() ?? '—',
                'student_deleted' => (bool) $student?->trashed(),
                'billed_years' => $group->pluck('academic_year_id')->unique()
                    ->map(fn ($id) => (string) ($yearNames[$id] ?? '?'))->values()->all(),
                'enrollments' => ($enrollments->get($studentId) ?? collect())->map(fn ($e) => [
                    'year' => $e->academicYear?->name,
                    'class' => $e->schoolClass?->name,
                    'admission_number' => $e->admission_number,
                    'status' => $e->status,
                ])->values()->all(),
                // Raw column value: amount_cents is cast to a Money object on the
                // model, which neither (int) nor a collection sum() can read.
                'payments' => $group->map(fn ($p) => [
                    'invoice_number' => $p->invoice_number,
                    'year' => (string) ($yearNames[$p->academic_year_id] ?? '?'),
                    'amount_cents' => (int) $p->getRawOriginal('amount_cents'),
                    'paid_at' => optional($p->paid_at)->toDateString() ?? substr((string) $p->paid_at, 0, 10),
                ])->values()->all(),
                'total_cents' => (int) $group->sum(fn ($p) => (int) $p->getRawOriginal('amount_cents')),
            ];
        })
            // Largest first: those are the ones worth fixing first.
            ->sortByDesc('total_cents')
            ->values();

        return [
            'students' => $rows->all(),
            'total_cents' => (int) $rows->sum('total_cents'),
        ];
    }
}
