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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Edit Student wizard's last step over HTTP: read the student's terms, save
 * corrections, and read them back. Exercises the route, the permission gate and
 * the school-ownership check — the parts TermBillingSyncTest does not reach.
 */
class StudentTermBillingEndpointTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Student $student;

    private AcademicYear $year;

    /** @var array<int, Term> */
    private array $terms = [];

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('superadmin');
        Role::findOrCreate('accountant');

        $this->school = School::create([
            'name' => 'Test Primary', 'code' => 'TSTP', 'slug' => 'test-primary', 'level' => 'primary',
        ]);

        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            $this->terms[$n] = Term::create([
                'academic_year_id' => $this->year->id, 'name' => "TERM {$n}", 'number' => $n,
                'start_date' => '2026-01-01', 'end_date' => '2026-03-31', 'is_current' => $n === 1,
            ]);
        }

        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'STANDARD TWO', 'level' => 2, 'sort_order' => 2,
        ]);

        $this->student = Student::create([
            'first_name' => 'Test', 'last_name' => 'Student', 'gender' => 'me', 'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'school_class_id' => $class->id, 'academic_year_id' => $this->year->id,
            'admission_number' => 'TST/0001/2026', 'status' => 'active', 'admitted_at' => '2026-01-10',
        ]);

        $this->accountant = User::factory()->create(['school_id' => $this->school->id]);
        $this->accountant->assignRole('accountant');

        // Three terms billed, the fourth missing — the case this feature exists for.
        $this->bill(1, 250000, 250000);
        $this->bill(2, 260000, 260000);
        $this->bill(3, 230000, 140000);
    }

    private function bill(int $termNumber, int $totalCents, int $paidCents): void
    {
        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'term_id' => $this->terms[$termNumber]->id, 'academic_year_id' => $this->year->id,
            'invoice_number' => "TSTP-2026-00000{$termNumber}", 'total_amount_cents' => $totalCents,
            'arrears_cents' => 0, 'discount_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(),
        ]);
        $invoice->lines()->create(['fee_item_id' => null, 'description' => 'Ada ya muhula', 'amount_cents' => $totalCents]);

        Payment::create([
            'invoice_id' => $invoice->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'amount_cents' => $paidCents, 'method' => 'cash', 'paid_at' => '2026-02-01',
            'recorded_by' => $this->accountant->id,
        ]);

        $invoice->refresh()->syncStatus();
    }

    private function asAccountant(): self
    {
        $this->actingAs($this->accountant, 'sanctum');

        return $this;
    }

    public function test_step_opens_with_the_students_existing_billing(): void
    {
        $response = $this->asAccountant()
            ->withHeader('X-School-Id', (string) $this->school->id)
            ->getJson("/api/students/{$this->student->id}/term-billing")
            ->assertOk();

        $byNumber = collect($response->json('terms'))->keyBy('term_number');

        $this->assertCount(4, $response->json('terms'));
        $this->assertSame(250000, $byNumber[1]['fee_amount_cents']);
        $this->assertSame(250000, $byNumber[1]['paid_cents']);
        $this->assertSame('paid', $byNumber[1]['status']);
        $this->assertSame(140000, $byNumber[3]['paid_cents']);
        $this->assertNull($byNumber[4]['invoice_id']);
    }

    public function test_saving_adds_the_missing_term_and_corrects_the_others(): void
    {
        $existing = collect($this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->getJson("/api/students/{$this->student->id}/term-billing")->json('terms'))->keyBy('term_number');

        $payload = ['terms' => [
            // Unchanged term, sent back exactly as the form holds it.
            [
                'term_id' => $existing[1]['term_id'], 'academic_year_id' => $existing[1]['academic_year_id'],
                'fee_amount_cents' => 250000,
                'payments' => [['id' => $existing[1]['payments'][0]['id'], 'amount_cents' => 250000, 'paid_at' => '2026-02-01', 'method' => 'cash']],
            ],
            // Corrected fee and a second instalment on term 3.
            [
                'term_id' => $existing[3]['term_id'], 'academic_year_id' => $existing[3]['academic_year_id'],
                'fee_amount_cents' => 240000,
                'payments' => [
                    ['id' => $existing[3]['payments'][0]['id'], 'amount_cents' => 140000, 'paid_at' => '2026-02-01', 'method' => 'cash'],
                    ['id' => null, 'amount_cents' => 60000, 'paid_at' => '2026-09-20', 'method' => 'mpesa'],
                ],
            ],
            // The missing fourth term.
            [
                'term_id' => $existing[4]['term_id'], 'academic_year_id' => $existing[4]['academic_year_id'],
                'fee_amount_cents' => 230000,
                'payments' => [['id' => null, 'amount_cents' => 90000, 'paid_at' => '2026-10-01', 'method' => 'cash']],
            ],
        ]];

        $response = $this->asAccountant()
            ->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('invoices_created'));
        $this->assertSame(1, $response->json('invoices_updated'));
        $this->assertSame(2, $response->json('payments_created'));

        // Four invoices, not seven: the three that existed were updated in place.
        $this->assertSame(4, Invoice::withoutGlobalScope('school')->where('student_id', $this->student->id)->count());

        $after = collect($response->json('terms'))->keyBy('term_number');
        $this->assertSame(240000, $after[3]['fee_amount_cents']);
        $this->assertSame(200000, $after[3]['paid_cents']);
        $this->assertSame('partial', $after[3]['status']);
        $this->assertSame(230000, $after[4]['fee_amount_cents']);
        $this->assertSame(90000, $after[4]['paid_cents']);
        // Term 1 was sent back unchanged and must stay a single payment.
        $this->assertSame(250000, $after[1]['paid_cents']);
        $this->assertCount(1, $after[1]['payments']);
    }

    public function test_saving_twice_changes_nothing_the_second_time(): void
    {
        $payload = ['terms' => [[
            'term_id' => $this->terms[4]->id, 'academic_year_id' => $this->year->id,
            'fee_amount_cents' => 230000, 'payments' => [],
        ]]];

        $this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", $payload)->assertOk();
        $second = $this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", $payload)->assertOk();

        $this->assertSame(0, $second->json('invoices_created'));
        $this->assertSame(4, Invoice::withoutGlobalScope('school')->where('student_id', $this->student->id)->count());
    }

    /**
     * Exactly what the Edit wizard's "add a missing term" card sends: ids as the
     * strings a <select> yields, no payment at all, so the parent can pay later.
     */
    public function test_add_a_missing_term_card_bills_it_for_later(): void
    {
        $payload = ['terms' => [[
            'term_id' => (string) $this->terms[4]->id,
            'academic_year_id' => (string) $this->year->id,
            'fee_amount_cents' => 23000000,
            'payments' => [],
        ]]];

        $response = $this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('invoices_created'));

        $invoice = Invoice::withoutGlobalScope('school')
            ->where('student_id', $this->student->id)
            ->where('term_id', $this->terms[4]->id)
            ->firstOrFail();

        $this->assertSame(23000000, (int) $invoice->getRawOriginal('total_amount_cents'));
        $this->assertSame(0, $invoice->paidCents());
        $this->assertSame('unpaid', $invoice->status instanceof \BackedEnum ? $invoice->status->value : $invoice->status);
        $this->assertSame(23000000, $invoice->balanceDueCents());
        // The student now has all four terms, each invoiced once.
        $this->assertSame(4, Invoice::withoutGlobalScope('school')->where('student_id', $this->student->id)->count());
    }

    /** The same card with a payment whose method was left on "Optional". */
    public function test_add_a_missing_term_card_with_a_payment_and_no_method(): void
    {
        $payload = ['terms' => [[
            'term_id' => (string) $this->terms[4]->id,
            'academic_year_id' => (string) $this->year->id,
            'fee_amount_cents' => 23000000,
            'payments' => [[
                'id' => null, 'amount_cents' => 9000000, 'paid_at' => '2026-10-01',
                'method' => null, 'reference_number' => null, 'notes' => 'Receipt #123',
            ]],
        ]]];

        $response = $this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", $payload)
            ->assertOk();

        $this->assertSame(1, $response->json('invoices_created'));
        $this->assertSame(1, $response->json('payments_created'));

        $term4 = collect($response->json('terms'))->firstWhere('term_number', 4);
        $this->assertSame(23000000, $term4['fee_amount_cents']);
        $this->assertSame(9000000, $term4['paid_cents']);
        $this->assertSame('partial', $term4['status']);
    }

    public function test_a_restricted_role_cannot_save(): void
    {
        Permission::findOrCreate('invoices.edit_restricted', 'web');
        $this->accountant->givePermissionTo('invoices.edit_restricted');

        $this->asAccountant()->withHeader('X-School-Id', (string) $this->school->id)
            ->putJson("/api/students/{$this->student->id}/term-billing", ['terms' => [[
                'term_id' => $this->terms[4]->id, 'academic_year_id' => $this->year->id, 'fee_amount_cents' => 1000,
            ]]])
            ->assertForbidden();

        $this->assertSame(3, Invoice::withoutGlobalScope('school')->where('student_id', $this->student->id)->count());
    }

    public function test_another_schools_user_is_refused(): void
    {
        $other = School::create(['name' => 'Other', 'code' => 'OTH', 'slug' => 'other', 'level' => 'primary']);
        $outsider = User::factory()->create(['school_id' => $other->id]);
        $outsider->assignRole('accountant');

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/students/{$this->student->id}/term-billing")
            ->assertForbidden();
    }
}
