<?php

namespace App\Services\Suppliers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Support\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every change to what a school owes a supplier goes through here, so the balance can
 * never drift from the bills and payments behind it:
 *
 *   balance = opening balance + approved bills - approved payments
 *
 * A bill owes nothing until the owner approves it. A payment above the school's approval
 * limit, recorded by anyone but the owner, waits for the owner and moves no money until then.
 */
class SupplierLedger
{
    public function recompute(Supplier $supplier): Supplier
    {
        $owed = (int) SupplierBill::where('supplier_id', $supplier->id)->whereIn('status', SupplierBill::OWED)->sum('total_cents');
        $paid = (int) SupplierPayment::where('supplier_id', $supplier->id)->where('status', SupplierPayment::APPROVED)->sum('amount_cents');

        $supplier->forceFill(['balance_cents' => (int) $supplier->opening_balance_cents + $owed - $paid])->save();

        return $supplier;
    }

    // ── bills ────────────────────────────────────────────────────────────────

    public function createBill(Supplier $supplier, array $data, array $items): SupplierBill
    {
        if ($supplier->status !== Supplier::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['supplier_id' => __('suppliers.err.supplier_not_active')]);
        }

        return DB::transaction(function () use ($supplier, $data, $items) {
            $bill = SupplierBill::create(Tenant::stripSchoolId($this->billAttributes($supplier, $data, $items) + [
                'school_id' => $supplier->school_id,
                'supplier_id' => $supplier->id,
                'status' => SupplierBill::PENDING,
                'recorded_by' => Tenant::actorId(),
            ]));
            $this->replaceItems($bill, $items);
            AuditLog::record('supplier_bill.created', $bill, [], $bill->toArray());
            // An owner who records a bill is asked on the spot whether to approve it; no notice to themselves.
            if (! auth()->user()?->hasAnyRole(['owner', 'superadmin'])) {
                SupplierNotifier::approvalNeeded('supplier_bill_pending', __('suppliers.notify.bill_pending'),
                    __('suppliers.notify.bill_pending_body', ['supplier' => $supplier->name, 'number' => $bill->bill_number, 'amount' => number_format($bill->total_cents / 100)]),
                    ['bill_id' => $bill->id, 'supplier_id' => $supplier->id]);
            }

            return $bill->load('items');
        });
    }

    /** Only a bill still waiting for (or refused) approval may be edited; editing re-submits it. */
    public function updateBill(SupplierBill $bill, array $data, array $items): SupplierBill
    {
        if (! in_array($bill->status, [SupplierBill::PENDING, SupplierBill::REJECTED], true)) {
            throw ValidationException::withMessages(['status' => __('suppliers.err.bill_locked')]);
        }

        return DB::transaction(function () use ($bill, $data, $items) {
            $before = $bill->toArray();
            $bill->update($this->billAttributes($bill->supplier, $data, $items) + ['status' => SupplierBill::PENDING]);
            $this->replaceItems($bill, $items);
            AuditLog::record('supplier_bill.updated', $bill, $before, $bill->toArray());

            return $bill->load('items');
        });
    }

    public function approveBill(SupplierBill $bill): SupplierBill
    {
        return DB::transaction(function () use ($bill) {
            $bill = SupplierBill::lockForUpdate()->findOrFail($bill->id);
            if ($bill->status !== SupplierBill::PENDING) {
                throw ValidationException::withMessages(['status' => __('suppliers.err.bill_not_pending')]);
            }
            $supplier = Supplier::lockForUpdate()->findOrFail($bill->supplier_id);

            // The expense is booked now, when the debt is incurred (not when it is paid):
            // 'credit' tells cash-flow not to count it again as money out.
            $expense = Expense::create(Tenant::stripSchoolId([
                'school_id' => $bill->school_id,
                'category_id' => $bill->expense_category_id,
                'amount_cents' => $bill->total_cents,
                'description' => __('suppliers.bill_expense_description', ['number' => $bill->bill_number, 'supplier' => $supplier->name]),
                'vendor' => $supplier->name,
                'receipt_reference' => $bill->bill_number,
                'expense_date' => $bill->bill_date,
                'recorded_by' => $bill->recorded_by,
                'approved_by' => Tenant::actorId(),
                'approved_at' => now(),
                'status' => 'approved',
                'supplier_id' => $supplier->id,
                'supplier_bill_id' => $bill->id,
                'payment_mode' => 'credit',
            ]));

            $bill->update([
                'status' => SupplierBill::APPROVED,
                'approved_by' => Tenant::actorId(),
                'approved_at' => now(),
                'expense_id' => $expense->id,
            ]);
            $this->recompute($supplier);
            AuditLog::record('supplier_bill.approved', $bill, [], ['status' => SupplierBill::APPROVED]);

            return $bill;
        });
    }

    public function rejectBill(SupplierBill $bill, string $reason): SupplierBill
    {
        if ($bill->status !== SupplierBill::PENDING) {
            throw ValidationException::withMessages(['status' => __('suppliers.err.bill_not_pending')]);
        }
        $bill->update(['status' => SupplierBill::REJECTED, 'cancel_reason' => $reason]);
        AuditLog::record('supplier_bill.rejected', $bill, [], ['reason' => $reason]);

        return $bill;
    }

    /** Cancel an approved bill that nothing has been paid against; the debt and its expense are reversed. */
    public function cancelBill(SupplierBill $bill, string $reason): SupplierBill
    {
        return DB::transaction(function () use ($bill, $reason) {
            $bill = SupplierBill::lockForUpdate()->findOrFail($bill->id);
            if (! in_array($bill->status, [SupplierBill::PENDING, SupplierBill::REJECTED, SupplierBill::APPROVED], true) || $bill->paid_cents > 0) {
                throw ValidationException::withMessages(['status' => __('suppliers.err.bill_has_payments')]);
            }
            $wasOwed = $bill->status === SupplierBill::APPROVED;

            if ($bill->expense_id) {
                Expense::whereKey($bill->expense_id)->delete();
            }
            $bill->update([
                'status' => SupplierBill::CANCELLED, 'expense_id' => null,
                'cancelled_by' => Tenant::actorId(), 'cancelled_at' => now(), 'cancel_reason' => $reason,
            ]);
            if ($wasOwed) {
                $this->recompute(Supplier::lockForUpdate()->findOrFail($bill->supplier_id));
            }
            AuditLog::record('supplier_bill.cancelled', $bill, [], ['reason' => $reason]);

            return $bill;
        });
    }

    // ── payments ─────────────────────────────────────────────────────────────

    /**
     * @param  array  $allocations  [['supplier_bill_id' => int, 'amount_cents' => int], ...]; empty = oldest bills first
     */
    public function createPayment(Supplier $supplier, array $data, array $allocations = []): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $allocations) {
            $supplier = Supplier::lockForUpdate()->findOrFail($supplier->id);
            $amount = (int) $data['amount_cents'];
            $wht = (int) ($data['wht_cents'] ?? 0);
            if ($wht < 0 || $wht > $amount) {
                throw ValidationException::withMessages(['wht_cents' => __('suppliers.err.wht_too_large')]);
            }

            $plan = $allocations ? $this->checkAllocations($supplier, $amount, $allocations) : $this->autoAllocate($supplier, $amount);

            $needsOwner = ! SupplierAccess::can('payment_approve') && $amount > (int) SupplierSettings::get('payment_approval_limit_cents');

            $payment = SupplierPayment::create(Tenant::stripSchoolId([
                'school_id' => $supplier->school_id,
                'supplier_id' => $supplier->id,
                'amount_cents' => $amount,
                'wht_cents' => $wht,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'efd_number' => $data['efd_number'] ?? null,
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'status' => $needsOwner ? SupplierPayment::PENDING : SupplierPayment::APPROVED,
                'recorded_by' => Tenant::actorId(),
                'approved_by' => $needsOwner ? null : Tenant::actorId(),
                'approved_at' => $needsOwner ? null : now(),
            ]));

            foreach ($plan as $billId => $cents) {
                SupplierPaymentAllocation::create(['supplier_payment_id' => $payment->id, 'supplier_bill_id' => $billId, 'amount_cents' => $cents]);
            }

            if (! $needsOwner) {
                $this->applyAllocations($payment);
                $this->recompute($supplier);
            }
            AuditLog::record($needsOwner ? 'supplier_payment.submitted' : 'supplier_payment.created', $payment, [], $payment->toArray());
            if ($needsOwner) {
                SupplierNotifier::approvalNeeded('supplier_payment_pending', __('suppliers.notify.payment_pending'),
                    __('suppliers.notify.payment_pending_body', ['supplier' => $supplier->name, 'amount' => number_format($amount / 100)]),
                    ['payment_id' => $payment->id, 'supplier_id' => $supplier->id]);
            }

            return $payment->load('allocations');
        });
    }

    public function approvePayment(SupplierPayment $payment): SupplierPayment
    {
        return DB::transaction(function () use ($payment) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SupplierPayment::PENDING) {
                throw ValidationException::withMessages(['status' => __('suppliers.err.payment_not_pending')]);
            }
            $supplier = Supplier::lockForUpdate()->findOrFail($payment->supplier_id);

            // Bills may have been paid or cancelled while this waited; check they still have room.
            foreach ($payment->allocations as $a) {
                $bill = SupplierBill::lockForUpdate()->findOrFail($a->supplier_bill_id);
                if (! $bill->isOpen() || $a->amount_cents > $bill->balanceCents()) {
                    throw ValidationException::withMessages(['status' => __('suppliers.err.allocation_stale', ['bill' => $bill->bill_number])]);
                }
            }

            $payment->update(['status' => SupplierPayment::APPROVED, 'approved_by' => Tenant::actorId(), 'approved_at' => now()]);
            $this->applyAllocations($payment);
            $this->recompute($supplier);
            AuditLog::record('supplier_payment.approved', $payment, [], ['status' => SupplierPayment::APPROVED]);

            return $payment;
        });
    }

    public function rejectPayment(SupplierPayment $payment, string $reason): SupplierPayment
    {
        if ($payment->status !== SupplierPayment::PENDING) {
            throw ValidationException::withMessages(['status' => __('suppliers.err.payment_not_pending')]);
        }
        $payment->update(['status' => SupplierPayment::REJECTED, 'void_reason' => $reason, 'voided_by' => Tenant::actorId(), 'voided_at' => now()]);
        AuditLog::record('supplier_payment.rejected', $payment, [], ['reason' => $reason]);

        return $payment;
    }

    /** An approved payment is never deleted: voiding it puts the debt back, with the reason on record. */
    public function voidPayment(SupplierPayment $payment, string $reason): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== SupplierPayment::APPROVED) {
                throw ValidationException::withMessages(['status' => __('suppliers.err.payment_not_voidable')]);
            }
            $supplier = Supplier::lockForUpdate()->findOrFail($payment->supplier_id);

            foreach ($payment->allocations as $a) {
                $bill = SupplierBill::lockForUpdate()->findOrFail($a->supplier_bill_id);
                $paid = max(0, $bill->paid_cents - $a->amount_cents);
                $bill->update(['paid_cents' => $paid, 'status' => $this->statusFor($bill, $paid)]);
            }
            $payment->update(['status' => SupplierPayment::VOID, 'void_reason' => $reason, 'voided_by' => Tenant::actorId(), 'voided_at' => now()]);
            $this->recompute($supplier);
            AuditLog::record('supplier_payment.voided', $payment, [], ['reason' => $reason]);

            return $payment;
        });
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function billAttributes(Supplier $supplier, array $data, array $items): array
    {
        $subtotal = 0;
        foreach ($items as $i) {
            $subtotal += (int) round((float) $i['quantity'] * (int) $i['unit_price_cents']);
        }
        $vat = array_key_exists('vat_cents', $data) && $data['vat_cents'] !== null
            ? (int) $data['vat_cents']
            : SupplierTax::vat($subtotal, $supplier);

        $due = $data['due_date'] ?? ($supplier->payment_terms_days
            ? Carbon::parse($data['bill_date'])->addDays($supplier->payment_terms_days)->toDateString()
            : null);

        return [
            'bill_number' => $data['bill_number'],
            'bill_date' => $data['bill_date'],
            'due_date' => $due,
            'subtotal_cents' => $subtotal,
            'vat_cents' => $vat,
            'total_cents' => $subtotal + $vat,
            'expense_category_id' => $data['expense_category_id'],
            'notes' => $data['notes'] ?? null,
            'cancel_reason' => null,
        ];
    }

    private function replaceItems(SupplierBill $bill, array $items): void
    {
        $bill->items()->delete();
        foreach ($items as $i) {
            $bill->items()->create([
                'description' => $i['description'],
                'quantity' => $i['quantity'],
                'unit' => $i['unit'] ?? null,
                'unit_price_cents' => (int) $i['unit_price_cents'],
                'line_total_cents' => (int) round((float) $i['quantity'] * (int) $i['unit_price_cents']),
                'inventory_item_id' => $i['inventory_item_id'] ?? null,
            ]);
        }
    }

    /** Validate user-chosen allocations: open bills of this supplier, within each bill's balance and the payment. */
    private function checkAllocations(Supplier $supplier, int $amount, array $allocations): array
    {
        $plan = [];
        foreach ($allocations as $a) {
            $plan[(int) $a['supplier_bill_id']] = ($plan[(int) $a['supplier_bill_id']] ?? 0) + (int) $a['amount_cents'];
        }
        if (array_sum($plan) > $amount) {
            throw ValidationException::withMessages(['allocations' => __('suppliers.err.allocation_exceeds_payment')]);
        }
        foreach ($plan as $billId => $cents) {
            $bill = SupplierBill::where('supplier_id', $supplier->id)->lockForUpdate()->find($billId);
            if (! $bill || ! $bill->isOpen() || $cents < 1 || $cents > $bill->balanceCents()) {
                throw ValidationException::withMessages(['allocations' => __('suppliers.err.allocation_invalid')]);
            }
        }

        return $plan;
    }

    /** Settle the oldest open bills first; whatever is left over stays on the supplier as an advance. */
    private function autoAllocate(Supplier $supplier, int $amount): array
    {
        $plan = [];
        $left = $amount;
        $bills = SupplierBill::where('supplier_id', $supplier->id)->whereIn('status', SupplierBill::OPEN)
            ->orderByRaw('due_date IS NULL, due_date')->orderBy('bill_date')->orderBy('id')->lockForUpdate()->get();
        foreach ($bills as $bill) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, $bill->balanceCents());
            if ($take > 0) {
                $plan[$bill->id] = $take;
                $left -= $take;
            }
        }

        return $plan;
    }

    private function applyAllocations(SupplierPayment $payment): void
    {
        foreach ($payment->allocations()->get() as $a) {
            $bill = SupplierBill::lockForUpdate()->findOrFail($a->supplier_bill_id);
            $paid = $bill->paid_cents + $a->amount_cents;
            $bill->update(['paid_cents' => $paid, 'status' => $this->statusFor($bill, $paid)]);
        }
    }

    private function statusFor(SupplierBill $bill, int $paid): string
    {
        return match (true) {
            $paid >= $bill->total_cents => SupplierBill::PAID,
            $paid > 0 => SupplierBill::PART_PAID,
            default => SupplierBill::APPROVED,
        };
    }
}
