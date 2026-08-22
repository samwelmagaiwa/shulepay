<?php

namespace App\Console\Commands;

use App\Models\PaymentPromise;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPromiseReminders extends Command
{
    protected $signature = 'promises:send-reminders';

    protected $description = 'Send SMS reminders for payment promises: guardian day-before, accountant on the day';

    public function handle(SmsService $sms): void
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        // ── 1. Day-before reminder → guardian ───────────────────────────────
        $dayBefore = PaymentPromise::with(['student', 'guardian', 'invoice'])
            ->where('status', 'pending')
            ->where('promised_date', $tomorrow)
            ->where('reminder_sent_day_before', false)
            ->get();

        foreach ($dayBefore as $promise) {
            $guardian = $promise->guardian;
            $phone = $guardian?->phone ?? $promise->student->guardians()
                ->wherePivot('is_primary', true)->first()?->phone;

            if (! $phone) {
                $this->warn("Promise #{$promise->id}: no guardian phone — skipped day-before SMS.");

                continue;
            }

            $message = SmsTemplates::promiseReminderGuardian($promise);
            $ok = $sms->send($phone, $message);
            $promise->update(['reminder_sent_day_before' => true]);

            $this->line($ok
                ? "✓ Day-before SMS sent to {$phone} (Promise #{$promise->id})"
                : "✗ Day-before SMS FAILED to {$phone} (Promise #{$promise->id})"
            );

            Log::info('[PromiseReminder] day-before', ['promise_id' => $promise->id, 'phone' => $phone, 'ok' => $ok]);
        }

        // ── 2. On-the-day reminder → accountant ─────────────────────────────
        $onDay = PaymentPromise::with(['student', 'guardian', 'invoice', 'recordedBy'])
            ->where('status', 'pending')
            ->where('promised_date', $today)
            ->where('reminder_sent_on_day', false)
            ->get();

        foreach ($onDay as $promise) {
            $accountant = $promise->recordedBy;
            $phone = $accountant?->phone;

            // Fallback: any active accountant/owner at the student's school
            if (! $phone) {
                $schoolId = $promise->student->currentEnrollment?->school_id;
                $phone = User::whereHas('roles', fn ($r) => $r->whereIn('name', ['accountant', 'owner']))
                    ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                    ->whereNotNull('phone')
                    ->value('phone');
            }

            $schoolName = $promise->student->currentEnrollment?->school?->name ?? 'Shule';
            $message = SmsTemplates::promiseReminderAccountant($promise, $schoolName);

            if ($phone) {
                $ok = $sms->send($phone, $message);
                $this->line($ok
                    ? "✓ On-day SMS sent to accountant {$phone} (Promise #{$promise->id})"
                    : "✗ On-day SMS FAILED to {$phone} (Promise #{$promise->id})"
                );
                Log::info('[PromiseReminder] on-day', ['promise_id' => $promise->id, 'phone' => $phone, 'ok' => $ok]);
            } else {
                $this->warn("Promise #{$promise->id}: no accountant phone — skipped on-day SMS.");
            }

            // Always mark as sent so we don't retry endlessly
            $promise->update(['reminder_sent_on_day' => true]);
        }

        $this->info("Done. Day-before: {$dayBefore->count()}, On-day: {$onDay->count()}");
    }
}
