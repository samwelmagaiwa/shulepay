<?php
namespace App\Services\Payments;

use App\Models\Receipt;

class ReceiptService {
    public function issue(int $studentId): Receipt {
        return Receipt::create([
            'receipt_number' => $this->nextReceiptNumber(),
            'student_id'     => $studentId,
            'issued_at'      => now(),
        ]);
    }

    private function nextReceiptNumber(): string {
        $year = date('Y');
        $last = Receipt::where('receipt_number', 'like', "RCP-{$year}-%")->max('receipt_number');
        $seq  = $last ? ((int) substr($last, -6)) + 1 : 1;
        return sprintf('RCP-%s-%06d', $year, $seq);
    }
}
