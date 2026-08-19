<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private SmsService $sms) {}

    /**
     * POST /api/sms/blast
     * Send an SMS blast to guardians of specific students.
     * Supports [Jina], [Kiasi], [Tarehe], [Shule] variable substitution.
     */
    public function blast(Request $request): JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:students,id',
            'message' => 'required|string|max:480',
        ]);

        $user = auth()->user();
        $schoolId = $user->school_id;
        $template = $request->message;
        $studentIds = $request->student_ids;

        $students = Student::with(['guardians' => function ($q) {
            $q->wherePivot('receives_sms', true);
        }])->whereIn('id', $studentIds)->get();

        $sent = 0;
        $failed = 0;

        foreach ($students as $student) {
            // Resolve message variables per student
            $invoice = $student->invoices()
                ->whereIn('status', ['unpaid', 'partial'])
                ->latest()
                ->first();

            $resolved = $invoice
                ? SmsTemplates::bulkReminder($template, $student, $invoice)
                : str_replace('[Jina]', $student->first_name, $template);

            // Determine target guardians
            $guardians = $student->guardians;
            $primary = $guardians->first(fn ($g) => (bool) ($g->pivot->is_primary ?? false));
            $targets = $primary ? collect([$primary]) : $guardians;

            foreach ($targets as $guardian) {
                $phone = $guardian->phone;
                if (! $phone) {
                    continue;
                }

                $ok = $this->sms->sendTemplate($phone, $resolved);
                $status = $ok ? 'sent' : 'failed';
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }

                SmsLog::create([
                    'school_id' => $schoolId,
                    'sender_id' => $user->id,
                    'recipient_phone' => $phone,
                    'message' => $resolved,
                    'status' => $status,
                    'student_id' => $student->id,
                    'sent_at' => now(),
                ]);
            }
        }

        AuditLog::record('sms_blast', null, [], [
            'student_ids' => $studentIds,
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return response()->json(['sent' => $sent, 'failed' => $failed]);
    }

    /**
     * POST /api/sms/reminder
     * Auto-blast fee reminders to guardians of students with outstanding balances.
     */
    public function reminder(Request $request): JsonResponse
    {
        $user = auth()->user();
        $schoolId = $user->school_id;

        $invoices = Invoice::with(['student.guardians', 'student.currentEnrollment'])
            ->where('school_id', $schoolId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $student = $invoice->student;
            if (! $student) {
                continue;
            }

            $balance = $invoice->balanceDueCents();
            if ($balance <= 0) {
                continue;
            }

            $message = SmsTemplates::paymentReminder($invoice, 'normal');

            $primaryGuardian = $student->guardians()
                ->wherePivot('is_primary', true)
                ->first();
            if (! $primaryGuardian || ! $primaryGuardian->phone) {
                continue;
            }

            $ok = $this->sms->sendTemplate($primaryGuardian->phone, $message);
            $status = $ok ? 'sent' : 'failed';
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }

            SmsLog::create([
                'school_id' => $schoolId,
                'sender_id' => $user->id,
                'recipient_phone' => $primaryGuardian->phone,
                'message' => $message,
                'status' => $status,
                'student_id' => $student->id,
                'sent_at' => now(),
            ]);
        }

        AuditLog::record('sms_reminder_blast', null, [], ['sent' => $sent, 'failed' => $failed]);

        return response()->json(['sent' => $sent, 'failed' => $failed]);
    }

    /**
     * GET /api/sms/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $query = SmsLog::with(['sender:id,name', 'student:id,first_name,last_name'])
            ->where('school_id', auth()->user()->school_id)
            ->latest('sent_at');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(50));
    }
}
