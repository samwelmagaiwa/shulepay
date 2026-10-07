<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Students\TermBillingSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Editing a registered student's term billing must correct what exists and only
 * create what is missing — the whole point is that it cannot duplicate the
 * invoices and payments already imported at registration.
 */
class TermBillingSyncTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Student $student;

    private AcademicYear $year;

    /** @var array<int, Term> */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('superadmin');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $this->actingAs($admin);

        $this->school = School::create([
            'name' => 'Magreth Primary', 'code' => 'MGRTH', 'slug' => 'magreth', 'level' => 'primary',
        ]);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            $this->terms[$n] = Term::create([
                'academic_year_id' => $this->year->id,
                'name' => "TERM {$n}",
                'number' => $n,
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'is_current' => $n === 1,
            ]);
        }

        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'STANDARD TWO', 'level' => 2, 'sort_order' => 2,
        ]);

        $this->student = Student::create([
            'first_name' => 'Baraka', 'last_name' => 'Marwa', 'gender' => 'me', 'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $this->year->id,
            'admission_number' => 'PRM/MGRTH/0231/2026',
            'status' => 'active',
            'admitted_at' => '2026-01-10',
        ]);
    }

    private function makeInvoice(int $termNumber, int $totalCents, int $paidCents = 0): Invoice
    {
        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'term_id' => $this->terms[$termNumber]->id,
            'academic_year_id' => $this->year->id,
            'invoice_number' => "MGRTH-2026-00083{$termNumber}",
            'total_amount_cents' => $totalCents,
            'arrears_cents' => 0,
            'discount_cents' => 0,
            'status' => 'unpaid',
            'generated_at' => now(),
        ]);
        $invoice->lines()->create(['fee_item_id' => null, 'description' => 'Ada ya muhula', 'amount_cents' => $totalCents]);

        if ($paidCents > 0) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'student_id' => $this->student->id,
                'school_id' => $this->school->id,
                'amount_cents' => $paidCents,
                'method' => 'cash',
                'paid_at' => '2026-02-01',
                'recorded_by' => auth()->id(),
            ]);
        }

        $invoice->refresh()->syncStatus();

        return $invoice->fresh();
    }

    private function sync(array $terms): array
    {
        return app(TermBillingSync::class)->sync($this->student, $this->school->id, $terms);
    }

    private function entry(int $termNumber, int $feeCents, array $payments = []): array
    {
        return [
            'term_id' => $this->terms[$termNumber]->id,
            'academic_year_id' => $this->year->id,
            'fee_amount_cents' => $feeCents,
            'payments' => $payments,
        ];
    }

    public function test_missing_term_is_created_with_its_payment(): void
    {
        $this->makeInvoice(1, 250000, 250000);

        $result = $this->sync([
            $this->entry(4, 230000, [['amount_cents' => 90000, 'paid_at' => '2026-10-01', 'method' => 'cash']]),
        ]);

        $this->assertSame(1, $result['invoices_created']);
        $this->assertSame(1, $result['payments_created']);

        $invoice = Invoice::withoutGlobalScope('school')->where('term_id', $this->terms[4]->id)->firstOrFail();
        $this->assertSame(230000, (int) $invoice->getRawOriginal('total_amount_cents'));
        $this->assertSame(90000, $invoice->paidCents());
        $this->assertSame('partial', $invoice->status instanceof \BackedEnum ? $invoice->status->value : $invoice->status);
    }

    public function test_existing_term_is_updated_not_duplicated(): void
    {
        $invoice = $this->makeInvoice(3, 230000, 140000);

        $result = $this->sync([$this->entry(3, 260000)]);

        $this->assertSame(0, $result['invoices_created']);
        $this->assertSame(1, $result['invoices_updated']);
        $this->assertSame(1, Invoice::withoutGlobalScope('school')->where('term_id', $this->terms[3]->id)->count());
        $this->assertSame(260000, (int) $invoice->fresh()->getRawOriginal('total_amount_cents'));
        // The fee line follows the total, so the two can never disagree.
        $this->assertSame(260000, (int) $invoice->fresh()->lines->first()->getRawOriginal('amount_cents'));
    }

    public function test_existing_payment_is_edited_in_place(): void
    {
        $invoice = $this->makeInvoice(3, 230000, 140000);
        $payment = $invoice->payments()->firstOrFail();

        $result = $this->sync([
            $this->entry(3, 230000, [
                ['id' => $payment->id, 'amount_cents' => 180000, 'paid_at' => '2026-05-05', 'method' => 'mpesa'],
            ]),
        ]);

        $this->assertSame(1, $result['payments_updated']);
        $this->assertSame(0, $result['payments_created']);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(180000, (int) $payment->fresh()->getRawOriginal('amount_cents'));
        $this->assertSame(50000, $invoice->fresh()->balanceDueCents());
    }

    public function test_total_below_what_is_already_paid_is_refused(): void
    {
        $this->makeInvoice(2, 260000, 260000);

        $this->expectException(ValidationException::class);
        $this->sync([$this->entry(2, 100000)]);
    }

    public function test_payments_cannot_exceed_the_invoice_total(): void
    {
        $this->makeInvoice(1, 250000, 250000);

        $this->expectException(ValidationException::class);
        $this->sync([
            $this->entry(1, 250000, [['amount_cents' => 50000, 'paid_at' => '2026-03-01', 'method' => 'cash']]),
        ]);
    }

    public function test_empty_rows_bill_nothing(): void
    {
        $result = $this->sync([$this->entry(2, 0), $this->entry(4, 0)]);

        $this->assertSame(0, $result['invoices_created']);
        $this->assertSame(0, Invoice::withoutGlobalScope('school')->count());
    }

    public function test_snapshot_lists_every_term_with_its_invoice(): void
    {
        $this->makeInvoice(1, 250000, 250000);
        $this->makeInvoice(3, 230000, 140000);

        $snapshot = app(TermBillingSync::class)->snapshot($this->student, $this->school->id);
        $byNumber = collect($snapshot['terms'])->keyBy('term_number');

        $this->assertCount(4, $snapshot['terms']);
        $this->assertSame(250000, $byNumber[1]['fee_amount_cents']);
        $this->assertSame(140000, $byNumber[3]['paid_cents']);
        $this->assertCount(1, $byNumber[3]['payments']);
        // Term 4 was never invoiced — it is offered so the gap can be filled.
        $this->assertNull($byNumber[4]['invoice_id']);
        // The period is shown on the form, so it must come through.
        $this->assertSame('2026-01-01', $byNumber[4]['start_date']);
        $this->assertSame('2026-03-31', $byNumber[4]['end_date']);
        $this->assertSame(0, $byNumber[4]['fee_amount_cents']);
    }
}
