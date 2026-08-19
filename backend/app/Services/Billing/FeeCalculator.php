<?php
namespace App\Services\Billing;

use App\Models\FeeStructure;
use App\Models\Student;
use App\Support\Money;

class FeeCalculator {
    /**
     * Calculate the total fee in cents for a student given a fee structure,
     * after applying all active discounts for that student.
     */
    public function calculate(Student $student, FeeStructure $structure): array {
        $items = $structure->feeItems;
        $subtotalCents = $items->sum(fn($item) => $item->amount_cents->cents());

        $discountCents = $student->discounts()
            ->whereNull('invoice_id')
            ->get()
            ->sum(fn($d) => $d->computedCents($subtotalCents));

        return [
            'subtotal_cents'  => $subtotalCents,
            'discount_cents'  => min($discountCents, $subtotalCents),
            'total_cents'     => max(0, $subtotalCents - $discountCents),
            'items'           => $items,
        ];
    }
}
