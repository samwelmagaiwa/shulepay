<?php

namespace App\Services\Billing;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceGenerator
{
    public function __construct(
        private FeeCalculator $calculator,
        private ArrearsService $arrears
    ) {}

    public function generateForStudent(Student $student, Term $term, AcademicYear $year): Invoice
    {
        // Find the student's enrollment for the school that owns this term
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('school_id', $year->school_id)
            ->where('status', 'active')
            ->firstOrFail();

        if (Invoice::allSchools()->where('student_id', $student->id)
            ->where('school_id', $enrollment->school_id)
            ->where('term_id', $term->id)->exists()) {
            throw new \RuntimeException(
                "Invoice already exists for {$enrollment->admission_number} in this term."
            );
        }

        $structure = FeeStructure::where([
            'school_id' => $enrollment->school_id,
            'school_class_id' => $enrollment->school_class_id,
            'term_id' => $term->id,
            'academic_year_id' => $year->id,
            'is_active' => true,
        ])->first();

        if (! $structure) {
            throw new \RuntimeException(
                "No active fee structure for class {$enrollment->school_class_id}, term {$term->id}."
            );
        }

        return DB::transaction(function () use ($student, $enrollment, $term, $year, $structure) {
            $calc = $this->calculator->calculate($student, $structure);
            $arrearsCents = $this->arrears->getCarryForwardCents($student, $term);

            $invoice = Invoice::withoutGlobalScope('school')->create([
                'student_id' => $student->id,
                'school_id' => $enrollment->school_id,
                'term_id' => $term->id,
                'academic_year_id' => $year->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'total_amount_cents' => $calc['subtotal_cents'],
                'discount_cents' => $calc['discount_cents'],
                'arrears_cents' => $arrearsCents,
                'status' => 'unpaid',
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]);

            foreach ($calc['items'] as $item) {
                $invoice->lines()->create([
                    'fee_item_id' => $item->id,
                    'description' => $item->name,
                    'amount_cents' => $item->amount_cents->cents(),
                ]);
            }

            if ($arrearsCents > 0) {
                $invoice->lines()->create([
                    'fee_item_id' => null,
                    'description' => 'Previous term balance (arrears)',
                    'amount_cents' => $arrearsCents,
                ]);
            }

            AuditLog::record('invoice_generated', $invoice, [], $invoice->toArray());

            // SMS: notify guardians of new invoice (outside the transaction return so it doesn't block)
            try {
                $invoice->loadMissing(['student', 'term']);
                $message = SmsTemplates::invoiceIssued($invoice);
                app(SmsService::class)->notifyGuardians($invoice->student, $message);
            } catch (\Throwable $e) {
                Log::warning('[InvoiceGenerator] Invoice SMS failed: '.$e->getMessage());
            }

            return $invoice;
        });
    }

    public function generateForTerm(Term $term, AcademicYear $year): array
    {
        $enrollments = Enrollment::where('school_id', $year->school_id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        $results = ['generated' => [], 'skipped' => [], 'errors' => []];

        foreach ($enrollments as $enrollment) {
            try {
                $inv = $this->generateForStudent($enrollment->student, $term, $year);
                $results['generated'][] = $inv->invoice_number;
            } catch (\Exception $e) {
                $results['skipped'][] = [
                    'student' => $enrollment->admission_number,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function nextInvoiceNumber(): string
    {
        $year = date('Y');
        $last = Invoice::allSchools()
            ->where('invoice_number', 'like', "INV-{$year}-%")
            ->max('invoice_number');
        $seq = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('INV-%s-%06d', $year, $seq);
    }
}
