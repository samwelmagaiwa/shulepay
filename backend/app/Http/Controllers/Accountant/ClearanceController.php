<?php
namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClearanceController extends Controller
{
    /**
     * GET /clearance/check?student_id=&academic_year_id=
     * Returns whether the student is cleared for the given academic year.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id'       => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $student      = Student::findOrFail($data['student_id']);
        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);

        $invoices = Invoice::allSchools()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $outstandingCents = 0;
        $invoiceData      = [];

        foreach ($invoices as $invoice) {
            $balance           = $invoice->balanceDueCents();
            $outstandingCents += $balance;

            $invoiceData[] = [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance_cents'  => $balance,
                'status'         => $invoice->status->value,
            ];
        }

        return response()->json([
            'cleared'           => $outstandingCents === 0,
            'outstanding_cents' => $outstandingCents,
            'invoices'          => $invoiceData,
        ]);
    }

    /**
     * POST /clearance/issue
     * Generates a PDF clearance certificate if student has no outstanding fees.
     */
    public function issue(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'student_id'       => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $student      = Student::with(['currentEnrollment.school', 'currentEnrollment.schoolClass'])->findOrFail($data['student_id']);
        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);

        $invoices = Invoice::allSchools()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $outstandingCents = $invoices->sum(fn($inv) => $inv->balanceDueCents());

        if ($outstandingCents > 0) {
            return response()->json([
                'message'           => 'Student has outstanding fees and cannot be cleared.',
                'outstanding_cents' => $outstandingCents,
            ], 422);
        }

        $enrollment = $student->currentEnrollment;
        $school     = $enrollment?->school ?? (app()->bound('active_school') ? app('active_school') : null);

        AuditLogger::log('clearance_issued', $student, [
            'after' => [
                'student_id'       => $student->id,
                'academic_year_id' => $academicYear->id,
                'issued_by'        => auth()->id(),
            ],
        ]);

        try {
            $message = SmsTemplates::clearanceIssued($student, $academicYear->name);
            app(SmsService::class)->notifyGuardians($student, $message);
        } catch (\Throwable $e) {
            Log::warning('[ClearanceController] Clearance SMS failed: ' . $e->getMessage());
        }

        $pdf = Pdf::loadView('pdf.clearance', [
            'student'          => $student,
            'enrollment'       => $enrollment,
            'school'           => $school,
            'academicYear'     => $academicYear,
            'issuedAt'         => now(),
            'issuedBy'         => auth()->user(),
        ]);

        $filename = 'clearance-' . $student->id . '-' . $academicYear->id . '.pdf';

        return $pdf->download($filename);
    }
}
