<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;

class ArrearsService
{
    /** Return the unpaid balance from the immediately preceding term */
    public function getCarryForwardCents(Student $student, Term $term): int
    {
        $previousTerm = Term::where('academic_year_id', $term->academic_year_id)
            ->where('number', $term->number - 1)
            ->first();

        if (! $previousTerm) {
            return 0;
        }

        $prevInvoice = Invoice::where('student_id', $student->id)
            ->where('term_id', $previousTerm->id)
            ->first();

        return $prevInvoice ? $prevInvoice->balanceDueCents() : 0;
    }
}
