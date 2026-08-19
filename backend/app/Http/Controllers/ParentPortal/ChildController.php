<?php
namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildController extends Controller {
    /**
     * GET /api/parent/children
     * Returns the authenticated parent's students with enrollment and balance info.
     */
    public function index(Request $request): JsonResponse {
        $user = $request->user();

        if (!$user->guardian) {
            return response()->json(['data' => []]);
        }

        $students = $user->guardian->students()
            ->with(['currentEnrollment.schoolClass', 'currentEnrollment.academicYear', 'invoices.payments'])
            ->get()
            ->map(function (Student $student) {
                $enrollment = $student->currentEnrollment;
                return [
                    'id'                       => $student->id,
                    'name'                     => $student->fullName(),
                    'gender'                   => $student->gender,
                    'status'                   => $student->status,
                    'photo'                    => $student->photo,
                    'outstanding_balance_cents' => $student->outstandingBalanceCents(),
                    'enrollment'               => $enrollment ? [
                        'admission_number' => $enrollment->admission_number,
                        'school_class'     => $enrollment->schoolClass?->only(['id', 'name']),
                        'academic_year'    => $enrollment->academicYear?->only(['id', 'name']),
                        'status'           => $enrollment->status,
                    ] : null,
                ];
            });

        return response()->json(['data' => $students]);
    }

    /**
     * GET /api/parent/children/{student}
     * Returns full details for a specific child (must belong to the parent).
     */
    public function show(Request $request, Student $student): JsonResponse {
        $this->authoriseOwnership($request->user(), $student);

        $student->load([
            'enrollments.schoolClass',
            'enrollments.academicYear',
            'invoices.lines',
            'invoices.payments',
            'guardians',
        ]);

        return response()->json([
            'id'                       => $student->id,
            'name'                     => $student->fullName(),
            'gender'                   => $student->gender,
            'date_of_birth'            => $student->date_of_birth,
            'status'                   => $student->status,
            'notes'                    => $student->notes,
            'photo'                    => $student->photo,
            'outstanding_balance_cents' => $student->outstandingBalanceCents(),
            'enrollments'              => $student->enrollments,
            'invoices'                 => $student->invoices,
            'guardians'                => $student->guardians->map(fn($g) => [
                'id'         => $g->id,
                'name'       => $g->fullName(),
                'phone'      => $g->phone,
                'relation'   => $g->pivot->relation,
                'is_primary' => $g->pivot->is_primary,
            ]),
        ]);
    }

    private function authoriseOwnership($user, Student $student): void {
        if (!$user->guardian) {
            abort(403, 'Ruhusa imekataliwa.');
        }
        $owns = $user->guardian->students()->where('students.id', $student->id)->exists();
        if (!$owns) {
            abort(403, 'Ruhusa imekataliwa.');
        }
    }
}
