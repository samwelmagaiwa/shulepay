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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deleting a student used to hard-delete their invoices and force-delete their
 * payments in the same transaction — irreversibly erasing the record that money
 * had been collected, with nothing in the confirm dialog to say so.
 *
 * Invoices now survive the student and are cleared deliberately through the
 * orphaned-invoice endpoints.
 */
class StudentDeletionKeepsInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Student $student;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create([
            'name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary',
        ]);
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'FIRST TERM', 'number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $school->id, 'name' => 'STANDARD ONE', 'sort_order' => 3,
        ]);

        $this->accountant = User::factory()->create(['school_id' => $school->id]);
        $this->accountant->assignRole('accountant');

        $this->student = Student::create([
            'first_name' => 'Juma', 'last_name' => 'Mfungo', 'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $this->student->id, 'school_id' => $school->id,
            'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'MSG-1', 'status' => 'active', 'admitted_at' => '2026-01-10',
        ]);

        $this->invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $this->student->id, 'school_id' => $school->id,
            'term_id' => $term->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'total_amount_cents' => 100000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'partial',
            'generated_at' => now(), 'generated_by' => $this->accountant->id,
        ]);
        Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->student->id,
            'school_id' => $school->id, 'amount_cents' => 40000,
            'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    public function test_deleting_a_student_leaves_the_invoice_and_payment_intact(): void
    {
        $this->withToken($this->token())
            ->deleteJson("/api/students/{$this->student->id}")
            ->assertOk();

        $this->assertSoftDeleted('students', ['id' => $this->student->id]);
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
        $this->assertDatabaseHas('payments', ['invoice_id' => $this->invoice->id]);

        // The enrollment must still be dropped, or headcounts keep the student.
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $this->student->id, 'status' => 'dropped',
        ]);
    }

    public function test_deletion_preview_reports_what_would_be_left_behind(): void
    {
        $res = $this->withToken($this->token())
            ->getJson("/api/students/{$this->student->id}/deletion-preview")
            ->assertOk();

        $this->assertSame(1, $res->json('invoice_count'));
        $this->assertSame(100000, $res->json('total_billed_cents'));
        $this->assertSame(1, $res->json('payment_count'));
        $this->assertSame(40000, $res->json('total_paid_cents'));
        $this->assertSame('INV-1', $res->json('invoices.0.invoice_number'));
        $this->assertSame('FIRST TERM', $res->json('invoices.0.term'));
    }

    public function test_orphaned_list_is_empty_until_the_student_is_deleted(): void
    {
        $this->assertSame(0, $this->withToken($this->token())
            ->getJson('/api/invoices/orphaned')->assertOk()->json('count'));

        $this->withToken($this->token())->deleteJson("/api/students/{$this->student->id}");

        $res = $this->withToken($this->token())->getJson('/api/invoices/orphaned')->assertOk();

        $this->assertSame(1, $res->json('count'));
        $this->assertSame(100000, $res->json('total_billed_cents'));
        $this->assertSame(40000, $res->json('total_paid_cents'));
        // The name has to survive the soft delete, or the row is unidentifiable.
        $this->assertStringContainsString('Juma', $res->json('rows.0.student_name'));
        $this->assertSame(1, $res->json('rows.0.payment_count'));
    }

    public function test_purging_an_orphan_removes_the_invoice_and_its_payments(): void
    {
        $this->withToken($this->token())->deleteJson("/api/students/{$this->student->id}");

        $res = $this->withToken($this->token())
            ->deleteJson('/api/invoices/orphaned', ['ids' => [$this->invoice->id]])
            ->assertOk();

        $this->assertSame(1, $res->json('deleted'));
        $this->assertSame(1, $res->json('payments_removed'));
        $this->assertDatabaseMissing('invoices', ['id' => $this->invoice->id]);
        $this->assertDatabaseMissing('payments', ['invoice_id' => $this->invoice->id]);
    }

    /**
     * The guard that matters: a live student's invoice must be untouchable
     * through this endpoint, however its id arrives.
     */
    public function test_purge_refuses_invoices_belonging_to_a_live_student(): void
    {
        $res = $this->withToken($this->token())
            ->deleteJson('/api/invoices/orphaned', ['ids' => [$this->invoice->id]])
            ->assertOk();

        $this->assertSame(0, $res->json('deleted'));
        $this->assertSame(1, $res->json('skipped'));
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
        $this->assertDatabaseHas('payments', ['invoice_id' => $this->invoice->id]);
    }
}
