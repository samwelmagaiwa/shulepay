<?php

namespace App\Services\Sms;

use App\Models\Payment;
use App\Models\SmsLog;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(private SmsGatewayInterface $gateway) {}

    /**
     * Send a single SMS. Formats Tanzanian numbers automatically.
     */
    public function send(string $phone, string $message): bool
    {
        $phone = $this->normaliseTz($phone);
        try {
            return $this->gateway->send($phone, $message);
        } catch (\Throwable $e) {
            Log::warning("[SmsService] send failed to {$phone}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the same message to multiple phones. Returns count of successful sends.
     */
    public function sendBulk(array $phones, string $message): int
    {
        $sent = 0;
        foreach ($phones as $phone) {
            if ($this->send($phone, $message)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Send a template message, splitting into parts if over 160 chars.
     */
    public function sendTemplate(string $phone, string $message): bool
    {
        $parts   = SmsTemplates::splitParts($message);
        $success = true;
        foreach ($parts as $part) {
            if (!$this->send($phone, $part)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Send a message to the primary guardian of a student (falls back to all sms-enabled guardians).
     * Logs each send to sms_logs. Returns number of messages sent.
     */
    public function notifyGuardians(Student $student, string $message): int
    {
        $sent = 0;

        $guardians = $student->guardians()->get();

        // Prefer primary contact; fall back to all receives_sms guardians
        $primary = $guardians->first(fn ($g) => (bool) ($g->pivot->is_primary ?? false));
        $targets = $primary
            ? collect([$primary])
            : $guardians->filter(fn ($g) => (bool) ($g->pivot->receives_sms ?? true));

        $schoolId = $student->currentEnrollment?->school_id;

        foreach ($targets as $guardian) {
            $phone = $guardian->phone;
            if (!$phone) {
                continue;
            }

            $ok = $this->sendTemplate($phone, $message);

            // Log every attempt (sent or failed)
            try {
                SmsLog::create([
                    'school_id'       => $schoolId,
                    'sender_id'       => auth()->id(),
                    'recipient_phone' => $phone,
                    'message'         => $message,
                    'status'          => $ok ? 'sent' : 'failed',
                    'student_id'      => $student->id,
                    'sent_at'         => now(),
                ]);
            } catch (\Throwable $logEx) {
                Log::warning('[SmsService] SmsLog write failed: ' . $logEx->getMessage());
            }

            if ($ok) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send a payment confirmation SMS to the student's primary guardian.
     *
     * @deprecated Use notifyGuardians() with SmsTemplates::paymentConfirmed() instead.
     */
    public function notifyPayment(Payment $payment): void
    {
        try {
            $message = SmsTemplates::paymentConfirmed(
                $payment->loadMissing(['invoice.student'])
            );
            $this->notifyGuardians($payment->invoice->student, $message);
        } catch (\Throwable $e) {
            Log::warning("[SmsService] notifyPayment failed: " . $e->getMessage());
        }
    }

    public function sendReminder(Payment $payment, string $phone, string $message): void
    {
        try {
            $this->gateway->send($this->normaliseTz($phone), $message);
        } catch (\Throwable $e) {
            Log::warning("[SmsService] sendReminder failed: " . $e->getMessage());
        }
    }

    private function normaliseTz(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '+')) {
            return $phone; // already international
        }
        return '+255' . ltrim($phone, '0');
    }
}
