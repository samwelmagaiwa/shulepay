<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Refund\StoreRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Invoice;
use App\Models\Refund;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $schoolId = auth()->user()->school_id;

        $query = Refund::with(['invoice', 'student', 'refundedBy'])
            ->whereHas('invoice', fn ($q) => $q->where('school_id', $schoolId))
            ->latest();

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        return RefundResource::collection($query->paginate(20));
    }

    public function store(StoreRefundRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Invoice $invoice */
        $invoice = Invoice::where('school_id', auth()->user()->school_id)
            ->findOrFail($data['invoice_id']);

        $paidCents = $invoice->paidCents();

        if ($data['amount_cents'] > $paidCents) {
            return response()->json([
                'message' => 'Refund amount exceeds total paid amount.',
                'paid_cents' => $paidCents,
            ], 422);
        }

        $refund = DB::transaction(function () use ($invoice, $data) {
            $refund = Refund::create([
                'student_id' => $invoice->student_id,
                'invoice_id' => $invoice->id,
                'amount_cents' => $data['amount_cents'],
                'reason' => $data['reason'],
                'method' => $data['method'],
                'refunded_by' => auth()->id(),
                'refunded_at' => now(),
            ]);

            // Reduce the total paid by creating a negative-equivalent: sync invoice status
            // We track refunds separately; invoice status reflects payments only.
            // A refund reduces effective balance => if previously Paid, it may go Partial.
            $invoice->syncStatus();

            AuditLogger::log('refund_created', $refund, [
                'before' => [],
                'after' => [
                    'invoice_id' => $invoice->id,
                    'amount_cents' => $data['amount_cents'],
                    'method' => $data['method'],
                    'reason' => $data['reason'],
                ],
            ]);

            return $refund;
        });

        $refund->load(['invoice.student', 'student', 'refundedBy']);

        try {
            $message = SmsTemplates::refundProcessed($refund);
            $refundStudent = $refund->student ?? $refund->invoice?->student;
            if ($refundStudent) {
                app(SmsService::class)->notifyGuardians($refundStudent, $message);
            }
        } catch (\Throwable $e) {
            Log::warning('[RefundController] Refund SMS failed: '.$e->getMessage());
        }

        return response()->json(new RefundResource($refund), 201);
    }

    public function destroy(Refund $refund): JsonResponse
    {
        // Strict school_id === comparison wrongly blocked a legitimate
        // multi-school accountant or superadmin whose primary school_id
        // differs from the refund's school — canAccessSchool() also honors
        // user_school_access grants and superadmin status.
        $schoolId = $refund->invoice?->school_id;
        abort_unless(
            $schoolId !== null && auth()->user()->canAccessSchool((int) $schoolId),
            403, 'Forbidden.'
        );

        DB::transaction(function () use ($refund) {
            $invoice = $refund->invoice;

            AuditLogger::log('refund_deleted', $refund, [
                'before' => [
                    'invoice_id' => $refund->invoice_id,
                    'amount_cents' => $refund->amount_cents->cents(),
                    'reason' => $refund->reason,
                ],
                'after' => [],
            ]);

            $refund->delete();

            if ($invoice) {
                $invoice->syncStatus();
            }
        });

        return response()->json(['message' => 'Refund deleted.']);
    }
}
