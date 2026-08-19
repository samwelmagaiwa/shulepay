<?php
namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstallmentResource;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InstallmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InstallmentPlan::with(['invoice', 'student'])->latest();

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas(
                'student',
                fn($q) =>
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
            );
        }

        return InstallmentResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'total_installments' => ['required', 'integer', 'min:2', 'max:12'],
            'start_date' => ['required', 'date'],
            'interval_days' => ['required', 'integer', 'min:7', 'max:90'],
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::allSchools()->findOrFail($data['invoice_id']);

        $balanceDue = $invoice->balanceDueCents();

        if ($balanceDue <= 0) {
            return response()->json(['message' => 'Invoice has no outstanding balance.'], 422);
        }

        // Check if installment plan already exists for this invoice
        $existing = InstallmentPlan::where('invoice_id', $invoice->id)->count();
        if ($existing > 0) {
            return response()->json(['message' => 'An installment plan already exists for this invoice.'], 422);
        }

        $installmentAmountCents = (int) ceil($balanceDue / $data['total_installments']);
        $startDate = Carbon::parse($data['start_date']);
        $intervalDays = (int) $data['interval_days'];

        $plans = DB::transaction(function () use ($invoice, $data, $installmentAmountCents, $startDate, $intervalDays) {
            $created = [];
            $total = (int) $data['total_installments'];

            for ($i = 1; $i <= $total; $i++) {
                $dueDate = (clone $startDate)->addDays(($i - 1) * $intervalDays);

                $created[] = InstallmentPlan::create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'installment_number' => $i,
                    'due_date' => $dueDate->toDateString(),
                    'amount_cents' => $installmentAmountCents,
                    'paid_amount_cents' => 0,
                    'status' => 'pending',
                ]);
            }

            AuditLogger::log('installment_plan_created', $created[0], [
                'after' => [
                    'invoice_id' => $invoice->id,
                    'total_installments' => $total,
                    'installment_amount_cents' => $installmentAmountCents,
                    'start_date' => $startDate->toDateString(),
                    'interval_days' => $intervalDays,
                ],
            ]);

            return $created;
        });

        $first = $plans[0];
        $first->load(['invoice.student', 'student']);

        try {
            $invoiceForSms = $first->invoice->loadMissing('student');
            $message = SmsTemplates::installmentPlanCreated($invoiceForSms, (int) $data['total_installments'], $installmentAmountCents);
            app(SmsService::class)->notifyGuardians($invoiceForSms->student, $message);
        } catch (\Throwable $e) {
            Log::warning('[InstallmentController] Plan created SMS failed: ' . $e->getMessage());
        }

        return response()->json(new InstallmentResource($first), 201);
    }

    /**
     * Bulk-create installment plans for all students in a class who have unpaid/partial invoices
     * for the given term. Already-planned invoices are skipped gracefully.
     */
    public function bulkByClass(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'total_installments' => ['required', 'integer', 'min:2', 'max:12'],
            'start_date' => ['required', 'date'],
            'interval_days' => ['required', 'integer', 'min:7', 'max:90'],
        ]);

        // All unpaid/partial invoices for this class + term that don't already have a plan
        $invoices = Invoice::allSchools()
            ->when(!empty($data['school_id']), fn($q) => $q->where('school_id', $data['school_id']))
            ->where('term_id', $data['term_id'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereHas(
                'student.currentEnrollment',
                fn($q) =>
                $q->where('school_class_id', $data['school_class_id'])
            )
            ->whereDoesntHave('installmentPlans')
            ->with(['student.currentEnrollment', 'student.guardians'])
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'message' => 'No eligible invoices found. All students in this class either have no outstanding balance or already have installment plans.',
                'created' => 0,
                'skipped' => 0,
            ], 200);
        }

        $created = 0;
        $startDate = Carbon::parse($data['start_date']);
        $intervalDays = (int) $data['interval_days'];
        $total = (int) $data['total_installments'];

        DB::transaction(function () use ($invoices, $data, $startDate, $intervalDays, $total, &$created) {
            foreach ($invoices as $invoice) {
                $balanceDue = $invoice->balanceDueCents();
                if ($balanceDue <= 0)
                    continue;

                $amountCents = (int) ceil($balanceDue / $total);

                for ($i = 1; $i <= $total; $i++) {
                    InstallmentPlan::create([
                        'invoice_id' => $invoice->id,
                        'student_id' => $invoice->student_id,
                        'installment_number' => $i,
                        'due_date' => (clone $startDate)->addDays(($i - 1) * $intervalDays)->toDateString(),
                        'amount_cents' => $amountCents,
                        'paid_amount_cents' => 0,
                        'status' => 'pending',
                    ]);
                }

                AuditLogger::log('installment_plan_created', $invoice, [], [
                    'invoice_id' => $invoice->id,
                    'total_installments' => $total,
                    'installment_amount_cents' => $amountCents,
                    'bulk' => true,
                ]);

                try {
                    $msg = SmsTemplates::installmentPlanCreated($invoice->loadMissing('student'), $total, $amountCents);
                    app(SmsService::class)->notifyGuardians($invoice->student, $msg);
                } catch (\Throwable $e) {
                    Log::warning('[InstallmentController] Bulk plan SMS failed: ' . $e->getMessage());
                }

                $created++;
            }
        });

        return response()->json([
            'message' => "Installment plans created for {$created} student(s).",
            'created' => $created,
        ], 201);
    }

    public function show(InstallmentPlan $installment): InstallmentResource
    {
        $installment->load(['invoice', 'student']);
        return new InstallmentResource($installment);
    }

    public function markPaid(InstallmentPlan $installment, Request $request): JsonResponse
    {
        $data = $request->validate([
            'installment_number' => ['required', 'integer', 'min:1'],
            'paid_amount_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        if ((int) $data['installment_number'] !== (int) $installment->installment_number) {
            throw ValidationException::withMessages([
                'installment_number' => 'Installment number does not match this record.',
            ]);
        }

        if ($installment->status === 'paid') {
            return response()->json(['message' => 'This installment is already marked as paid.'], 422);
        }

        // Determine the amount to record — adding new amount to existing paid amount
        $expectedCents = (int) $installment->amount_cents->cents();
        $previouslyPaidCents = (int) $installment->paid_amount_cents->cents();

        $newPaymentCents = isset($data['paid_amount_cents'])
            ? (int) $data['paid_amount_cents']
            : ($expectedCents - $previouslyPaidCents);

        $totalPaidCents = $previouslyPaidCents + $newPaymentCents;
        // Cap the total to the expected amount
        $totalPaidCents = min($totalPaidCents, $expectedCents);

        $isFullPayment = $totalPaidCents >= $expectedCents;
        $newStatus = $isFullPayment ? 'paid' : 'partial';

        DB::transaction(function () use ($installment, $totalPaidCents, $newStatus) {
            $installment->update([
                'paid_amount_cents' => $totalPaidCents,
                'status' => $newStatus,
            ]);

            AuditLogger::log('installment_marked_paid', $installment, [
                'before' => ['status' => $installment->getOriginal('status')],
                'after' => [
                    'status' => $newStatus,
                    'paid_amount_cents' => $totalPaidCents,
                    'installment_number' => $installment->installment_number,
                    'invoice_id' => $installment->invoice_id,
                ],
            ]);

            // Only check overall invoice completion on full installment payment
            if ($newStatus === 'paid') {
                $totalSiblings = InstallmentPlan::where('invoice_id', $installment->invoice_id)->count();
                $paidSiblings = InstallmentPlan::where('invoice_id', $installment->invoice_id)
                    ->where('status', 'paid')
                    ->count();

                if ($paidSiblings >= $totalSiblings) {
                    $invoice = $installment->invoice;
                    if ($invoice) {
                        $invoice->syncStatus();
                    }
                }
            }
        });

        $installment->refresh()->load(['invoice.student', 'student']);

        try {
            $invoiceForSms = $installment->invoice->loadMissing('student');
            $paidStudent = $installment->student ?? $invoiceForSms->student;
            $totalSiblings = InstallmentPlan::where('invoice_id', $installment->invoice_id)->count();
            $remainingCents = (int) InstallmentPlan::where('invoice_id', $installment->invoice_id)
                ->whereIn('status', ['pending', 'partial'])
                ->sum('amount_cents');

            $message = SmsTemplates::installmentPaid(
                (int) $installment->installment_number,
                $totalSiblings,
                $paidStudent->first_name,
                $remainingCents
            );
            app(SmsService::class)->notifyGuardians($paidStudent, $message);
        } catch (\Throwable $e) {
            Log::warning('[InstallmentController] Mark paid SMS failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => $isFullPayment ? 'Installment marked as paid.' : 'Partial payment recorded.',
            'installment' => new InstallmentResource($installment),
        ]);
    }
}
