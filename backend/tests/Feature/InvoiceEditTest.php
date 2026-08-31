<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Correcting an invoice amount has to move three things together: the total,
 * the fee line the receipt prints, and the status, which is derived from what
 * is still owed rather than stored on its own.
 */
class InvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'FOURTH TERM', 'number' => 4, 'start_date' => '2026-09-30', 'end_date' => '2026-10-25', 'is_current' => false]);

        $this->accountant = User::factory()->create(['school_id' => $school->id]);
        $this->accountant->assignRole('accountant');

        $student = Student::create(['first_name' => 'Shakila', 'last_name' => 'Abdul', 'status' => 'active']);

        $this->invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'term_id' => $term->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'total_amount_cents' => 20000000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid',
            'generated_at' => now(), 'generated_by' => $this->accountant->id,
        ]);
        InvoiceLine::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Ada ya muhula', 'amount_cents' => 20000000,
        ]);
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    public function test_editing_the_total_updates_the_fee_line_too(): void
    {
        $this->withToken($this->token())
            ->putJson("/api/invoices/{$this->invoice->id}", ['total_amount_cents' => 30000000])
            ->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id, 'total_amount_cents' => 30000000,
        ]);
        // A line left at the old figure would make the printed receipt disagree
        // with the invoice it is printed from.
        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $this->invoice->id, 'amount_cents' => 30000000,
        ]);
    }

    public function test_status_follows_the_new_total(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->invoice->student_id,
            'school_id' => $this->invoice->school_id, 'amount_cents' => 20000000,
            'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);
        $this->invoice->syncStatus();
        $this->assertSame('paid', $this->invoice->fresh()->status->value);

        // Raising the total on a settled invoice must reopen it as partial.
        $this->withToken($this->token())
            ->putJson("/api/invoices/{$this->invoice->id}", ['total_amount_cents' => 25000000])
            ->assertOk();

        $this->assertSame('partial', $this->invoice->fresh()->status->value);
    }

    public function test_lowering_the_total_below_what_was_paid_is_refused(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->invoice->student_id,
            'school_id' => $this->invoice->school_id, 'amount_cents' => 20000000,
            'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token())
            ->putJson("/api/invoices/{$this->invoice->id}", ['total_amount_cents' => 5000000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('total_amount_cents');

        // Unchanged: an overpayment must not be buried by flipping it to Paid.
        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id, 'total_amount_cents' => 20000000,
        ]);
    }

    public function test_an_itemised_invoice_is_refused_rather_than_guessed_at(): void
    {
        InvoiceLine::create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Usafiri', 'amount_cents' => 5000000,
        ]);

        $this->withToken($this->token())
            ->putJson("/api/invoices/{$this->invoice->id}", ['total_amount_cents' => 30000000])
            ->assertStatus(422);

        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id, 'total_amount_cents' => 20000000,
        ]);
    }
}
