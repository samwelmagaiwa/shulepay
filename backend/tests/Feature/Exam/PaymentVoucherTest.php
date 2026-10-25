<?php

namespace Tests\Feature\Exam;

use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Pdf\PaymentVoucherBuilder;
use App\Support\AmountInWords;

/**
 * Payment vouchers: one for an approved supplier payment, an approved expense and a paid salary, never for
 * anything that is only waiting. The amount in words must agree with the figures.
 */
class PaymentVoucherTest extends ExamTestCase
{
    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maker = User::factory()->create(['school_id' => $this->school->id, 'name' => 'Salum Mrema']);
    }

    private function expense(string $status): Expense
    {
        $cat = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);

        return Expense::create([
            'school_id' => $this->school->id, 'category_id' => $cat->id, 'amount_cents' => 4_500_000, 'description' => 'Petrol for the school bus',
            'vendor' => 'Lake Oil', 'receipt_reference' => 'INV-77', 'expense_date' => now()->toDateString(), 'recorded_by' => $this->maker->id,
            'approved_by' => $status === 'approved' ? $this->maker->id : null, 'approved_at' => $status === 'approved' ? now() : null,
            'status' => $status, 'payment_mode' => 'paid',
        ]);
    }

    private function supplierPayment(string $status): SupplierPayment
    {
        $supplier = Supplier::create(['school_id' => $this->school->id, 'code' => 'SUP-0001', 'name' => 'Mwanza Stationers Ltd', 'tin' => '112-458-306', 'bank_name' => 'NMB Bank', 'bank_account_number' => '2041500387']);

        return SupplierPayment::create([
            'supplier_id' => $supplier->id, 'school_id' => $this->school->id, 'amount_cents' => 158_120_000, 'wht_cents' => 3_162_400,
            'method' => 'bank', 'reference' => 'NMB-TRF-1', 'efd_number' => 'EFD-9', 'payment_date' => now()->toDateString(),
            'status' => $status, 'recorded_by' => $this->maker->id,
        ]);
    }

    private function salary(string $status): Payroll
    {
        $emp = Employee::create(['school_id' => $this->school->id, 'staff_number' => 'ST-1', 'full_name' => 'Grace Mwakalinga', 'role' => 'Head Teacher', 'basic_salary_cents' => 180_000_000, 'hire_date' => now()->toDateString(), 'status' => 'active']);

        return Payroll::create([
            'school_id' => $this->school->id, 'employee_id' => $emp->id, 'month' => now()->month, 'year' => now()->year,
            'basic_salary_cents' => 180_000_000, 'allowances_cents' => 0, 'paye_cents' => 41_300_000, 'nssf_employee_cents' => 18_000_000,
            'deductions_cents' => 59_300_000, 'net_salary_cents' => 120_700_000, 'payment_method' => 'bank', 'status' => $status,
            'payment_date' => $status === 'paid' ? now()->toDateString() : null, 'recorded_by' => $this->maker->id,
        ]);
    }

    public function test_the_amount_in_words_agrees_with_the_figure(): void
    {
        $this->assertSame('One million five hundred and forty-nine thousand five hundred and seventy-six shillings only', AmountInWords::shillings(154_957_600));
        $this->assertSame('Zero shillings only', AmountInWords::shillings(0));
        $this->assertSame('Twelve shillings only', AmountInWords::shillings(1_200));
        $this->assertSame('Milioni moja na elfu mia tatu na arobaini na mbili tu', AmountInWords::shillings(134_200_000, 'sw'));
        $this->assertSame('Elfu moja tu', AmountInWords::shillings(100_000, 'sw'));
    }

    public function test_each_kind_of_voucher_is_a_pdf_with_its_own_number(): void
    {
        $this->actingAsRole('admin');
        $expense = $this->expense('approved');
        $payment = $this->supplierPayment('approved');
        $salary = $this->salary('paid');

        foreach ([['expense', $expense->id, 'EX'], ['supplier-payment', $payment->id, 'SP'], ['payroll', $salary->id, 'PR']] as [$type, $id, $code]) {
            $res = $this->get("/api/vouchers/{$type}/{$id}")->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $res->getContent());
            $this->assertStringContainsString("PV-{$code}-", $res->headers->get('Content-Disposition'));
        }
    }

    public function test_the_voucher_carries_the_net_amount_the_payee_is_handed(): void
    {
        $this->actingAsRole('admin');
        $v = app(PaymentVoucherBuilder::class)->build('supplier-payment', $this->supplierPayment('approved')->id);
        $this->assertSame(158_120_000, $v['gross_cents']);
        $this->assertSame([['Withholding tax (WHT)', 3_162_400]], $v['deductions']);
        $this->assertSame(154_957_600, $v['net_cents']);
        $this->assertSame('Mwanza Stationers Ltd', $v['payee']);
        $this->assertSame('Salum Mrema', $v['prepared_by']);

        $s = app(PaymentVoucherBuilder::class)->build('payroll', $this->salary('paid')->id);
        $this->assertSame(120_700_000, $s['net_cents']);
        $this->assertSame([['PAYE', 41_300_000], ['NSSF', 18_000_000]], $s['deductions']);
    }

    public function test_nothing_that_is_only_waiting_gets_a_voucher(): void
    {
        $this->actingAsRole('admin');
        $this->get('/api/vouchers/expense/'.$this->expense('pending')->id)->assertStatus(422);
        $this->get('/api/vouchers/supplier-payment/'.$this->supplierPayment('pending_approval')->id)->assertStatus(422);
        $this->get('/api/vouchers/payroll/'.$this->salary('pending')->id)->assertStatus(422);
        $this->get('/api/vouchers/invoice/1')->assertNotFound();
    }

    public function test_a_parent_cannot_open_a_voucher(): void
    {
        $id = $this->expense('approved')->id;
        $this->actingAsRole('parent');
        $this->get("/api/vouchers/expense/{$id}")->assertForbidden();
    }
}
