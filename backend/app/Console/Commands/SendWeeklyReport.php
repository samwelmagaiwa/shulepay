<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyReport extends Command
{
    protected $signature   = 'reports:weekly';
    protected $description = 'Generate and dispatch weekly fee-collections summary for every school';

    public function handle(): int
    {
        $from = now()->subDays(7)->startOfDay();
        $to   = now()->endOfDay();

        $schools = School::where('is_active', true)->get();

        $this->info("Generating weekly reports for {$schools->count()} school(s) ({$from->toDateString()} – {$to->toDateString()})");

        foreach ($schools as $school) {
            $this->processSchool($school, $from, $to);
        }

        $this->info('Weekly reports complete.');

        return self::SUCCESS;
    }

    private function processSchool(School $school, Carbon $from, Carbon $to): void
    {
        // Aggregate payments for this school in the past 7 days
        $payments = Payment::allSchools()
            ->where('school_id', $school->id)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(amount_cents) as total_cents')
            ->first();

        $count      = (int) ($payments->count ?? 0);
        $totalCents = (int) ($payments->total_cents ?? 0);

        // Aggregate pending (unpaid / partial) invoices
        $pendingCents = (int) Invoice::allSchools()
            ->where('school_id', $school->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum('total_amount_cents');

        $summary = [
            'school_id'    => $school->id,
            'school_name'  => $school->name,
            'period_from'  => $from->toDateString(),
            'period_to'    => $to->toDateString(),
            'payment_count'=> $count,
            'total_cents'  => $totalCents,
            'pending_cents'=> $pendingCents,
        ];

        // Audit log entry
        AuditLog::create([
            'user_id'    => null,
            'action'     => 'weekly_report_generated',
            'model_type' => School::class,
            'model_id'   => $school->id,
            'before'     => null,
            'after'      => $summary,
            'ip_address' => null,
            'user_agent' => 'Scheduler',
        ]);

        // Compose and send SMS to school owner
        $message = SmsTemplates::weeklySummary($school->name, $totalCents, $pendingCents, $count);

        try {
            $owner = User::where('school_id', $school->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                ->first();

            if ($owner?->phone) {
                app(SmsService::class)->sendTemplate($owner->phone, $message);
                $this->line("  [{$school->name}] SMS sent to owner ({$owner->phone})");
            } else {
                $this->line("  [{$school->name}] No owner phone found — SMS skipped");
                Log::channel('daily')->info('[reports:weekly] No owner phone', ['school' => $school->name]);
            }
        } catch (\Throwable $e) {
            Log::warning('[reports:weekly] SMS failed for ' . $school->name . ': ' . $e->getMessage());
        }

        $totalTzs = number_format($totalCents / 100, 2);
        $this->line("  [{$school->name}] {$count} payments | TZS {$totalTzs}");
    }
}
