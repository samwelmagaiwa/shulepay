<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Models\Student;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * A single consolidated receipt/statement covering every invoice a student
 * has — used by the Invoices page's "Print Receipt" action on a student's
 * collapsed row, so one printout shows all terms' debts, payments made, and
 * the remaining balance instead of one receipt per invoice.
 */
class StudentStatementPdf
{
    public function generate(Student $student): string
    {
        $student->loadMissing([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
            'currentEnrollment.academicYear',
            'guardians',
        ]);

        $invoices = Invoice::allSchools()
            ->with(['payments', 'term'])
            ->where('student_id', $student->id)
            ->orderBy('due_date')
            ->get()
            ->map(function (Invoice $inv) {
                $gross = $inv->total_amount_cents->cents()
                    + $inv->arrears_cents->cents()
                    - $inv->discount_cents->cents();
                $paid = $inv->paidCents();

                return (object) [
                    'invoice_number' => $inv->invoice_number,
                    'term' => $inv->term?->name,
                    'gross_cents' => $gross,
                    'paid_cents' => $paid,
                    'balance_cents' => max(0, $gross - $paid),
                    'status' => $inv->status instanceof \BackedEnum ? $inv->status->value : $inv->status,
                    'payments' => $inv->payments->map(fn ($p) => (object) [
                        'paid_at' => $p->paid_at,
                        'amount_cents' => $p->amount_cents->cents(),
                        'method' => $p->method,
                        'reference_number' => $p->reference_number,
                    ]),
                ];
            });

        $totalInvoiced = $invoices->sum('gross_cents');
        $totalPaid = $invoices->sum('paid_cents');
        $totalBalance = max(0, $totalInvoiced - $totalPaid);

        $enrollment = $student->currentEnrollment;
        $school = $enrollment?->school;
        $settings = $school?->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $lh = SchoolLetterhead::for($school);
        $appName = $lh['name'];
        $appTagline = $lh['tagline'];
        $logoBase64 = $lh['logo'];

        return Pdf::loadView('pdf.student_statement', [
            'student' => $student,
            'enrollment' => $enrollment,
            'invoices' => $invoices,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalBalance' => $totalBalance,
            'appName' => $appName,
            'appTagline' => $appTagline,
            'logoBase64' => $logoBase64,
            'lh' => $lh,
            // A4 portrait — wider than the A5 this was originally designed at, per
            // request: A5 left the printed statement looking cramped/narrow next
            // to the viewer it's previewed in. The per-payment receipt stays A5.
        ])->setPaper('a4')->output();
    }
}
