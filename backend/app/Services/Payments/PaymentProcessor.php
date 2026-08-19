<?php
namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentProcessor {
    public function __construct(
        private ReceiptService $receiptService,
        private SmsService $smsService
    ) {}

    public function record(Invoice $invoice, array $data): Payment {
        return DB::transaction(function () use ($invoice, $data) {
            $receipt = $this->receiptService->issue($invoice->student_id);

            $payment = Payment::withoutGlobalScope('school')->create([
                'invoice_id'       => $invoice->id,
                'student_id'       => $invoice->student_id,
                'school_id'        => $invoice->school_id,
                'receipt_id'       => $receipt->id,
                'amount_cents'     => $data['amount_cents'],
                'method'           => $data['method'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at'          => $data['paid_at'] ?? now(),
                'recorded_by'      => auth()->id(),
                'notes'            => $data['notes'] ?? null,
            ]);

            $invoice->syncStatus();

            AuditLog::record('payment_recorded', $payment, [], $payment->toArray());

            $payment->loadMissing(['invoice.student']);
            try {
                $message = SmsTemplates::paymentConfirmed($payment);
                $this->smsService->notifyGuardians($payment->invoice->student, $message);
            } catch (\Throwable $e) {
                Log::warning('[PaymentProcessor] Payment SMS failed: ' . $e->getMessage());
            }

            return $payment->load(['receipt', 'invoice.student']);
        });
    }
}
