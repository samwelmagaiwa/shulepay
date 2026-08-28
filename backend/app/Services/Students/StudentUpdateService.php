<?php

namespace App\Services\Students;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Full-wizard edit counterpart to StudentRegistrationService — used by the
 * "Edit Student" flow when it reopens AddStudentModal in edit mode instead of
 * the old single-page form. Deliberately separate from the simple
 * StudentController::update() path (UpdateStudentRequest) so that path's
 * narrower contract and existing callers are untouched.
 *
 * Guardrail: this never modifies an existing Invoice/Payment/Discount. A fee
 * or discount entered here only takes effect if generate_new_invoice is
 * explicitly true, in which case it creates one additional invoice — exactly
 * like registering a new fee, never a retroactive edit of billing history
 * already recorded.
 */
class StudentUpdateService
{
    public function __construct(private StudentRegistrationService $registrationService) {}

    public function update(Student $student, array $data, ?UploadedFile $photo = null): Student
    {
        return DB::transaction(function () use ($student, $data, $photo) {
            $before = $student->toArray();

            if ($photo) {
                $data['photo'] = $photo->store('student-photos', 'public');
            }

            $fields = [
                'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'status',
                'sponsorship_type',
                'birth_certificate_no', 'nationality', 'religion',
                'blood_group', 'allergies', 'medical_conditions',
                'address', 'region', 'district', 'ward', 'street', 'place',
                'photo', 'notes',
            ];
            $studentData = array_filter(
                array_intersect_key($data, array_flip($fields)),
                fn ($v) => ! is_null($v)
            );
            if (array_key_exists('sponsorship_type', $studentData) || array_key_exists('status', $studentData)) {
                $studentData['status'] = Student::effectiveStatus(
                    $studentData['sponsorship_type'] ?? $student->sponsorship_type,
                    $studentData['status'] ?? $student->status
                );
            }
            if (! empty($studentData)) {
                $student->update($studentData);
            }

            // Class change only — school/academic year/term are left alone. Moving
            // a student between schools or years is a transfer/promotion action
            // with its own implications for existing invoices, not something an
            // identity-edit form should do silently.
            if (! empty($data['school_class_id'])) {
                $student->currentEnrollment?->update(['school_class_id' => $data['school_class_id']]);
            }

            if (! empty($data['guardians'])) {
                $this->syncGuardians($student, $data['guardians']);
            }

            // invoices has a unique (student_id, school_id, term_id) constraint —
            // a term can only ever have one invoice, so this is only attempted
            // when the student's current term doesn't already have one. Silently
            // skipping (rather than erroring) keeps a save that also touched
            // identity/guardian fields from failing over an invoice that already
            // exists for an unrelated reason.
            if (! empty($data['generate_new_invoice'])) {
                $enrollment = $student->currentEnrollment;
                $hasInvoiceForTerm = $enrollment && Invoice::withoutGlobalScope('school')
                    ->where('student_id', $student->id)
                    ->where('school_id', $enrollment->school_id)
                    ->where('term_id', $enrollment->term_id)
                    ->exists();

                if ($enrollment && ! $hasInvoiceForTerm) {
                    $this->registrationService->generateInvoice($student, [
                        'school_id' => $enrollment->school_id,
                        'school_class_id' => $enrollment->school_class_id,
                        'academic_year_id' => $enrollment->academic_year_id,
                        'term_id' => $enrollment->term_id,
                        'total_tuition_fee_cents' => $data['total_tuition_fee_cents'] ?? 0,
                        'sponsored_amount_cents' => $data['sponsored_amount_cents'] ?? 0,
                        'sponsorship_type' => $data['sponsorship_type'] ?? $student->sponsorship_type,
                        'discount_type' => $data['discount_type'] ?? null,
                        'discount_amount_cents' => $data['discount_amount_cents'] ?? 0,
                        'opening_balance_cents' => $data['opening_balance_cents'] ?? 0,
                    ]);
                }
            }

            AuditLog::record('student_updated', $student, $before, $student->fresh()->toArray());

            return $student->load(['currentEnrollment.schoolClass', 'currentEnrollment.school', 'guardians']);
        });
    }

    /**
     * Adds/updates guardians present in the payload and detaches (pivot-only —
     * the Guardian record itself may be shared with siblings, so it is never
     * deleted here) any currently-linked guardian the edit form no longer
     * lists, matching what removing a guardian card in the UI implies.
     */
    private function syncGuardians(Student $student, array $guardiansData): void
    {
        $keptGuardianIds = [];

        foreach ($guardiansData as $gData) {
            $guardian = $this->registrationService->resolveGuardian($gData);
            $keptGuardianIds[] = $guardian->id;

            $student->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'relation' => $gData['relationship'],
                    'is_primary' => (bool) ($gData['is_primary_contact'] ?? false),
                    'receives_sms' => true,
                ],
            ]);
        }

        DB::table('guardian_student')
            ->where('student_id', $student->id)
            ->whereNotIn('guardian_id', $keptGuardianIds)
            ->delete();
    }
}
