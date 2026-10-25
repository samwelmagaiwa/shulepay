<?php

namespace App\Services\Pdf;

use App\Models\Expense;
use App\Models\Payroll;
use App\Models\School;
use App\Models\SupplierPayment;
use App\Support\Tenant;
use Carbon\Carbon;

/**
 * Turns an approved supplier payment, an approved expense or a paid salary into one shape the voucher view prints:
 * who is paid, how much, what for, the deductions, and who prepared and approved it.
 *
 * A voucher is only issued for money that has actually been signed off (approved or paid); anything else would be
 * a promise to pay, not a record of payment.
 */
class PaymentVoucherBuilder
{
    public const TYPES = ['supplier-payment', 'expense', 'payroll'];

    /** @throws \DomainException when the record is not yet approved or paid (the controller turns it into a 422) */
    public function build(string $type, int $id): array
    {
        return match ($type) {
            'supplier-payment' => $this->supplierPayment(SupplierPayment::with(['supplier', 'school', 'recorder', 'approver', 'allocations.bill'])->findOrFail($id)),
            'expense' => $this->expense(Expense::with(['category', 'school', 'recorder', 'approver', 'supplier', 'supplierBill'])->findOrFail($id)),
            'payroll' => $this->payroll(Payroll::with(['employee', 'school', 'recorder'])->findOrFail($id)),
            default => throw new \InvalidArgumentException('Unknown voucher type'),
        };
    }

    public static function number(string $code, ?Carbon $date, int $id): string
    {
        return sprintf('PV-%s-%s-%05d', $code, ($date ?? now())->format('Y'), $id);
    }

    private function school(?School $own): ?School
    {
        return $own ?? Tenant::school() ?? (app()->bound('active_school') ? app('active_school') : null);
    }

    private function supplierPayment(SupplierPayment $p): array
    {
        if ($p->status !== SupplierPayment::APPROVED) {
            throw new \DomainException(__('messages.voucher_not_approved'));
        }
        $s = $p->supplier;
        $methods = ['cash' => 'Cash', 'bank' => 'Bank transfer', 'mpesa' => 'M-Pesa', 'tigo_pesa' => 'Tigo Pesa', 'airtel_money' => 'Airtel Money', 'halopesa' => 'HaloPesa', 'cheque' => 'Cheque', 'other' => 'Other'];

        return [
            'type' => 'supplier_payment',
            'number' => self::number('SP', $p->payment_date, $p->id),
            'date' => $p->payment_date,
            'school' => $this->school($p->school),
            'payee' => $s?->name,
            'payee_lines' => array_values(array_filter([
                [__('pdf.voucher.supplier_code'), $s?->code],
                [__('pdf.voucher.contact'), $s?->contact_name],
                [__('pdf.voucher.phone'), $s?->phone],
                [__('pdf.voucher.tin'), $s?->tin],
                [__('pdf.voucher.bank'), $s?->bank_name ? trim($s->bank_name.' · '.$s->bank_account_number) : null],
            ], fn ($l) => filled($l[1]))),
            'method' => $methods[$p->method] ?? ucfirst((string) $p->method),
            'reference' => $p->reference,
            'efd' => $p->efd_number,
            'purpose' => __('pdf.voucher.purpose_supplier'),
            'items' => $p->allocations->map(fn ($a) => [
                'description' => __('pdf.voucher.bill_no', ['no' => $a->bill?->bill_number ?? '#'.$a->supplier_bill_id]),
                'cents' => (int) $a->amount_cents,
            ])->values()->all(),
            'gross_cents' => (int) $p->amount_cents,
            'deductions' => $p->wht_cents > 0 ? [[__('pdf.voucher.wht'), (int) $p->wht_cents]] : [],
            'net_cents' => $p->netCents(),
            'notes' => $p->notes,
            'prepared_by' => $p->recorder?->name,
            'prepared_at' => $p->created_at,
            'approved_by' => $p->approver?->name,
            'approved_at' => $p->approved_at,
        ];
    }

    private function expense(Expense $e): array
    {
        if ($e->status !== 'approved') {
            throw new \DomainException(__('messages.voucher_not_approved'));
        }
        $credit = $e->payment_mode === 'credit';
        $amount = (int) $e->amount_cents->cents();

        return [
            'type' => 'expense',
            'number' => self::number('EX', $e->expense_date, $e->id),
            'date' => $e->expense_date,
            'school' => $this->school($e->school),
            'payee' => $e->supplier?->name ?: ($e->vendor ?: '—'),
            'payee_lines' => array_values(array_filter([
                [__('pdf.voucher.category'), $e->category?->name],
                [__('pdf.voucher.supplier_code'), $e->supplier?->code],
                [__('pdf.voucher.phone'), $e->supplier?->phone],
                [__('pdf.voucher.tin'), $e->supplier?->tin],
            ], fn ($l) => filled($l[1]))),
            'method' => $credit ? __('pdf.voucher.on_credit') : __('pdf.voucher.paid'),
            'reference' => $e->receipt_reference,
            'efd' => null,
            'purpose' => $e->description,
            'items' => [['description' => $e->description, 'cents' => $amount]],
            'gross_cents' => $amount,
            'deductions' => [],
            'net_cents' => $amount,
            'notes' => $e->notes,
            'prepared_by' => $e->recorder?->name,
            'prepared_at' => $e->created_at,
            'approved_by' => $e->approver?->name,
            'approved_at' => $e->approved_at,
        ];
    }

    private function payroll(Payroll $r): array
    {
        if ($r->status !== 'paid') {
            throw new \DomainException(__('messages.voucher_not_approved'));
        }
        $emp = $r->employee;
        $c = fn ($v) => (int) ($v?->cents() ?? 0);
        $basic = $c($r->basic_salary_cents);
        $allow = $c($r->allowances_cents);
        $period = Carbon::create((int) $r->year, (int) $r->month, 1)->translatedFormat('F Y');

        return [
            'type' => 'payroll',
            'number' => self::number('PR', $r->payment_date ?? Carbon::create((int) $r->year, (int) $r->month, 1), $r->id),
            'date' => $r->payment_date ?? Carbon::create((int) $r->year, (int) $r->month, 1)->endOfMonth(),
            'school' => $this->school($r->school),
            'payee' => $emp?->full_name,
            'payee_lines' => array_values(array_filter([
                [__('pdf.voucher.staff_no'), $emp?->staff_number],
                [__('pdf.voucher.role'), $emp?->role],
                [__('pdf.voucher.department'), $emp?->department],
            ], fn ($l) => filled($l[1]))),
            'method' => ['cash' => 'Cash', 'bank' => 'Bank transfer', 'mpesa' => 'M-Pesa'][$r->payment_method] ?? ucfirst((string) $r->payment_method),
            'reference' => null,
            'efd' => null,
            'purpose' => __('pdf.voucher.purpose_salary', ['period' => $period]),
            'items' => array_values(array_filter([
                ['description' => __('pdf.voucher.basic_salary'), 'cents' => $basic],
                $allow > 0 ? ['description' => __('pdf.voucher.allowances'), 'cents' => $allow] : null,
            ])),
            'gross_cents' => $basic + $allow,
            'deductions' => array_values(array_filter([
                $c($r->paye_cents) > 0 ? ['PAYE', $c($r->paye_cents)] : null,
                $c($r->nssf_employee_cents) > 0 ? ['NSSF', $c($r->nssf_employee_cents)] : null,
            ])),
            'net_cents' => $c($r->net_salary_cents),
            'notes' => $r->notes,
            'prepared_by' => $r->recorder?->name,
            'prepared_at' => $r->created_at,
            'approved_by' => null,
            'approved_at' => $r->payment_date,
        ];
    }
}
