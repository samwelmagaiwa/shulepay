<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\Students\TermBillingSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Term-by-term billing of an already-registered student, used by the Edit
 * Student wizard's last step.
 *
 * Separate from InvoiceController deliberately: that edits one invoice at a
 * time, while this reconciles a student's whole year — correcting fees,
 * recording instalments, and adding a term that was never invoiced — in one
 * transaction.
 */
class StudentTermBillingController extends Controller
{
    public function __construct(private TermBillingSync $sync) {}

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($student);

        return response()->json(
            $this->sync->snapshot($student, $this->schoolIdFor($request, $student))
        );
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($student);
        $this->authorizeEditing();

        $data = $request->validate([
            'terms' => ['required', 'array'],
            'terms.*.term_id' => ['required', 'integer', 'exists:terms,id'],
            'terms.*.academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'terms.*.fee_amount_cents' => ['required', 'integer', 'min:0'],
            'terms.*.payments' => ['sometimes', 'array'],
            'terms.*.payments.*.id' => ['sometimes', 'nullable', 'integer', 'exists:payments,id'],
            'terms.*.payments.*.amount_cents' => ['required_with:terms.*.payments', 'integer', 'min:0'],
            'terms.*.payments.*.paid_at' => ['required_with:terms.*.payments', 'nullable', 'date'],
            'terms.*.payments.*.method' => ['sometimes', 'nullable', 'string', 'max:30'],
            'terms.*.payments.*.reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $schoolId = $this->schoolIdFor($request, $student);
        $result = $this->sync->sync($student, $schoolId, $data['terms']);

        AuditLogger::log('student.term_billing_updated', $student, $result);

        return response()->json($result + $this->sync->snapshot($student, $schoolId));
    }

    /**
     * The same inverted permission the invoice and payment editors use: holding
     * it REMOVES the ability. Superadmin is exempt explicitly, since it holds
     * every permission implicitly and must never lock itself out.
     */
    private function authorizeEditing(): void
    {
        $user = auth()->user();
        if ($user?->isSuperAdmin()) {
            return;
        }

        $permissions = $user?->getAllPermissions();
        abort_if(
            (bool) $permissions?->contains('name', 'invoices.edit_restricted'),
            403,
            __('Editing invoices is restricted for your role.')
        );
        abort_if(
            (bool) $permissions?->contains('name', 'payments.edit_restricted'),
            403,
            __('Editing payments is restricted for your role.')
        );
    }

    /**
     * Which school's billing is being edited: the active school when the user
     * can reach it, otherwise the student's own enrollment. Never trust the
     * header alone — it would let one school's figures be written under another.
     */
    private function schoolIdFor(Request $request, Student $student): int
    {
        $enrolled = $student->enrollments()->pluck('school_id')->unique();

        $requested = $request->filled('school_id')
            ? $request->integer('school_id')
            : ((int) $request->header('X-School-Id') ?: null);

        if ($requested && $enrolled->contains($requested)) {
            return (int) $requested;
        }

        $current = $student->currentEnrollment?->school_id ?? $enrolled->first();
        abort_if(! $current, 422, __('This student has no enrollment, so there is nothing to bill.'));

        return (int) $current;
    }

    /** @see StudentController::authorizeStudentAccess() — same ownership rule. */
    private function authorizeStudentAccess(Student $student): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $schoolIds = $student->enrollments()->pluck('school_id')->unique();
        abort_unless(
            $schoolIds->contains(fn ($id) => $user->canAccessSchool((int) $id)),
            403,
            __('You do not have access to this student.')
        );
    }
}
