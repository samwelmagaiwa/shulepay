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
                    // Only shown when a term settled through exactly one payment — a
                    // term split across several payments (different methods) has no
                    // single "method" that would be accurate to print.
                    'method_label' => $inv->payments->count() === 1
                        ? $inv->payments->first()->method?->label()
                        : null,
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

        // A statement covers many invoices/receipts at once, so it has no single
        // receipt number of its own — this reference lets one printout still be
        // identified/reissued later, the same way a receipt number does.
        $statementNumber = 'STM-'.($enrollment?->admission_number ?: $student->id).'-'.now()->format('Ymd');

        return Pdf::loadView('pdf.student_statement', [
            'student' => $student,
            'enrollment' => $enrollment,
            'statementNumber' => $statementNumber,
            'invoices' => $invoices,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalBalance' => $totalBalance,
            'appName' => $appName,
            'appTagline' => $appTagline,
            'logoBase64' => $logoBase64,
            'lh' => $lh,
            // A4 portrait — wider than the A5 this was originally designed at:
            // A5 left the statement looking cramped next to the viewer it is
            // previewed in. Receipts and reports are A4 too, so every printed
            // document now shares one paper size.
        ])->setPaper('a4')->output();
    }
}
