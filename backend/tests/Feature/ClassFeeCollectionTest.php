<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DashboardLock;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Reporting\ClassFeeCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Each payment must land in exactly one class — the one its student was in for
 * the invoice's year — and class figures plus unassigned must equal the total.
 */
class ClassFeeCollectionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicYear $y2026;

    private AcademicYear $y2025;

    private SchoolClass $formOne;

    private SchoolClass $formTwo;

    private User $user;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Sekondari', 'code' => 'SEK', 'slug' => 'sek', 'level' => 'secondary']);
        $this->y2025 = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_current' => false]);
        $this->y2026 = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->formOne = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'FORM ONE', 'sort_order' => 999]);
        $this->formTwo = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'FORM TWO', 'sort_order' => 999]);
        $this->user = User::factory()->create(['school_id' => $this->school->id]);
    }

    private function student(): Student
    {
        return Student::create(['first_name' => 'S'.(++$this->seq), 'last_name' => 'Test', 'status' => 'active']);
    }

    private function enrol(Student $s, SchoolClass $c, AcademicYear $y, string $status = 'active'): void
    {
        Enrollment::withoutGlobalScope('school')->create([
            'student_id' => $s->id, 'school_id' => $this->school->id, 'school_class_id' => $c->id,
            'academic_year_id' => $y->id, 'admission_number' => 'ADM-'.(++$this->seq),
            'status' => $status, 'admitted_at' => $y->start_date,
        ]);
    }

    private function pay(Student $s, AcademicYear $y, int $cents): void
    {
        $term = Term::firstOrCreate(
            ['academic_year_id' => $y->id, 'number' => 1],
            ['name' => 'FIRST TERM', 'start_date' => $y->start_date, 'end_date' => $y->end_date, 'is_current' => false]
        );
        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $s->id, 'school_id' => $this->school->id, 'term_id' => $term->id,
            'academic_year_id' => $y->id, 'invoice_number' => 'INV-'.(++$this->seq),
            'total_amount_cents' => $cents, 'discount_cents' => 0, 'arrears_cents' => 0,
            'status' => 'paid', 'generated_at' => now(), 'generated_by' => $this->user->id,
        ]);
        Payment::withoutGlobalScope('school')->create([
            'invoice_id' => $invoice->id, 'student_id' => $s->id, 'school_id' => $this->school->id,
            'amount_cents' => $cents, 'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $this->user->id,
        ]);
    }

    private function run_(): array
    {
        return app(ClassFeeCollection::class)->for($this->school->id);
    }

    public function test_collections_and_students_per_real_class_in_order(): void
    {
        $a = $this->student();
        $this->enrol($a, $this->formOne, $this->y2026);
        $this->pay($a, $this->y2026, 30000000);
        $b = $this->student();
        $this->enrol($b, $this->formTwo, $this->y2026);
        $this->pay($b, $this->y2026, 20000000);
        $c = $this->student();
        $this->enrol($c, $this->formTwo, $this->y2026);

        $r = $this->run_();

        $this->assertSame(['FORM ONE', 'FORM TWO'], array_column($r['classes'], 'class_name'));
        $this->assertSame([30000000, 20000000], array_column($r['classes'], 'collected_cents'));
        $this->assertSame([1, 2], array_column($r['classes'], 'students'));
        $this->assertSame(50000000, $r['total_cents']);
    }

    /** Promoted student: last year's fee stays with last year's class, and is not counted twice. */
    public function test_payment_goes_to_the_class_of_the_invoice_year_only(): void
    {
        $s = $this->student();
        $this->enrol($s, $this->formOne, $this->y2025, 'completed');
        $this->enrol($s, $this->formTwo, $this->y2026);
        $this->pay($s, $this->y2025, 10000000);
        $this->pay($s, $this->y2026, 12000000);

        $r = collect($this->run_()['classes'])->keyBy('class_name');

        $this->assertSame(10000000, $r['FORM ONE']['collected_cents']);
        $this->assertSame(12000000, $r['FORM TWO']['collected_cents']);
    }

    /** No matching enrollment: reported, not dropped, so the total stays exact. */
    public function test_unmatched_payments_are_unassigned_not_lost(): void
    {
        $s = $this->student();
        $this->enrol($s, $this->formOne, $this->y2026);
        $this->pay($s, $this->y2026, 5000000);
        $this->pay($s, $this->y2025, 7000000); // billed for a year with no enrollment

        $r = $this->run_();

        $this->assertSame(7000000, $r['unassigned_cents']);
        $this->assertSame(12000000, $r['total_cents']);
    }

    /** The list must explain exactly the dashboard figure, student by student. */
    public function test_unassigned_list_matches_the_unassigned_figure(): void
    {
        $a = $this->student();
        $this->enrol($a, $this->formOne, $this->y2026);
        $this->pay($a, $this->y2026, 5000000);   // linked
        $this->pay($a, $this->y2025, 7000000);   // no 2025 enrollment

        $b = $this->student();
        $this->enrol($b, $this->formTwo, $this->y2026);
        $this->pay($b, $this->y2025, 3000000);   // no 2025 enrollment

        $service = app(ClassFeeCollection::class);
        $list = $service->unassigned($this->school->id);

        $this->assertSame($service->for($this->school->id)['unassigned_cents'], $list['total_cents']);
        $this->assertSame(10000000, $list['total_cents']);
        $this->assertCount(2, $list['students']);

        // Largest first, with the years billed and the years actually enrolled,
        // which is what tells the reader how to fix it.
        $first = $list['students'][0];
        $this->assertSame($a->id, $first['student_id']);
        $this->assertSame(['2025'], $first['billed_years']);
        $this->assertSame('2026', $first['enrollments'][0]['year']);
        $this->assertSame(7000000, $first['payments'][0]['amount_cents']);
    }

    public function test_the_endpoint_is_withheld_while_the_dashboard_is_locked(): void
    {
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $this->user->assignRole('accountant');
        DashboardLock::create([
            'user_id' => $this->user->id,
            'code_hash' => Hash::make('1234'),
            'locked_at' => now(),
        ]);

        $this->withToken($this->user->createToken('t')->plainTextToken)
            ->getJson('/api/dashboard/unassigned-fees')
            ->assertStatus(423);
    }

    public function test_an_empty_school_returns_no_classes_and_zero(): void
    {
        $this->assertSame(['classes' => [], 'unassigned_cents' => 0, 'total_cents' => 0], $this->run_());
    }
}
