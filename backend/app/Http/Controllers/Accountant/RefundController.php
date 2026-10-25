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
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Refund::with(['invoice', 'student', 'refundedBy'])->latest();

        // In central-DB mode scope to the active school; in tenant mode every
        // row already belongs to this school so no filter is needed.
        if (! $this->isTenantMode()) {
            $schoolId = $this->schoolIdForQuery($request);
            if ($schoolId) {
                $query->whereHas('invoice', fn ($q) => $q->where('school_id', $schoolId));
            }
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        $query->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('refunded_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('refunded_at', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim((string) $request->search), '%_\\').'%';
                $q->where(fn ($w) => $w->where('reason', 'like', $term)
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', $term))
                    ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('middle_name', 'like', $term)));
            });

        // Totals of everything the filters match, not just the page on screen
        $sum = (clone $query)->reorder()->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount_cents), 0) AS cents')->first();
        $month = (clone $query)->reorder()->whereBetween('refunded_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_cents');

        return RefundResource::collection($query->paginate(min(max($request->integer('per_page', 20), 5), 200)))->additional(['summary' => [
            'count' => (int) ($sum->n ?? 0),
            'total_cents' => (int) ($sum->cents ?? 0),
            'month_cents' => (int) $month,
        ]]);
    }

    public function store(StoreRefundRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Invoice $invoice */
        // In tenant mode every invoice already belongs to this school.
        $invoice = $this->isTenantMode()
            ? Invoice::findOrFail($data['invoice_id'])
            : Invoice::where('school_id', auth()->user()->school_id)->findOrFail($data['invoice_id']);

        $paidCents = $invoice->paidCents();

        if ($data['amount_cents'] > $paidCents) {
            return response()->json([
                'message' => __('messages.refund_amount_exceeds_total_paid_amount'),
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
                'refunded_by' => Tenant::actorId(),
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
        // Taking a refund out of the books is the owner's decision, not the accountant's
        if (! auth()->user()?->hasAnyRole(['owner', 'superadmin'])) {
            return response()->json(['message' => __('messages.refund_delete_admin_only')], 403);
        }

        // Strict school_id === comparison wrongly blocked a legitimate
        // multi-school accountant or superadmin whose primary school_id
        // differs from the refund's school — canAccessSchool() also honors
        // user_school_access grants and superadmin status.
        $schoolId = $refund->invoice?->school_id;
        abort_unless(
            $schoolId !== null && auth()->user()->canAccessSchool((int) $schoolId),
            403, __('messages.forbidden')
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

        return response()->json(['message' => __('messages.refund_deleted')]);
    }
}
