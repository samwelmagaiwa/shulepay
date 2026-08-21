<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\Pdf\ReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    /**
     * GET /api/parent/statement
     * Returns invoices with lines and payments for a student (must be own child).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
        ]);

        $user = $request->user();
        $student = Student::findOrFail($request->student_id);

        $this->authoriseOwnership($user, $student);

        $query = Invoice::allSchools()->with(['lines', 'payments', 'term', 'academicYear'])
            ->where('student_id', $student->id);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $invoices = $query->latest('generated_at')->get();

        $totalBilled = $invoices->sum(fn ($inv) => $inv->total_amount_cents->cents() + $inv->arrears_cents->cents() - $inv->discount_cents->cents());
        $totalPaid = $invoices->sum(fn ($inv) => $inv->paidCents());
        $totalBalance = max(0, $totalBilled - $totalPaid);

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->fullName()],
            'invoices' => $invoices,
            'summary' => [
                'total_billed_cents' => $totalBilled,
                'total_paid_cents' => $totalPaid,
                'total_balance_cents' => $totalBalance,
            ],
        ]);
    }

    /**
     * GET /api/parent/statement/pdf
     */
    public function download(Request $request): Response
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
        ]);

        $user = $request->user();
        $student = Student::findOrFail($request->student_id);

        $this->authoriseOwnership($user, $student);

        $query = Invoice::allSchools()->with(['lines', 'payments', 'term', 'academicYear'])
            ->where('student_id', $student->id);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $invoices = $query->latest('generated_at')->get();

        try {
            $pdf = app(ReportPdf::class);
            $content = $pdf->studentStatement($student, $invoices);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="statement-'.$student->id.'.pdf"',
            ]);
        } catch (\Throwable) {
            return response()->json(['message' => 'PDF haipatikani kwa sasa.'], 501);
        }
    }

    private function authoriseOwnership($user, Student $student): void
    {
        if (! $user->guardian) {
            abort(403, 'Access denied.');
        }
        $owns = $user->guardian->students()->where('students.id', $student->id)->exists();
        if (! $owns) {
            abort(403, 'Access denied.');
        }
    }
}
