<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Payments were create-only, so a mis-keyed amount could not be corrected at
 * all. Editing one moves the invoice balance, so the status has to be re-derived
 * alongside it; reversing one soft-deletes so the reversal stays evidenced.
 */
class PaymentEditTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Invoice $invoice;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'FIRST TERM', 'number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-03-30', 'is_current' => true]);

        $this->accountant = User::factory()->create(['school_id' => $school->id]);
        $this->accountant->assignRole('accountant');

        $student = Student::create(['first_name' => 'Shakila', 'last_name' => 'Abdul', 'status' => 'active']);

        $this->invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'term_id' => $term->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'total_amount_cents' => 25000000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'paid',
            'generated_at' => now(), 'generated_by' => $this->accountant->id,
        ]);

        // Keyed in as a full settlement when only part was received.
        $this->payment = Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $student->id,
            'school_id' => $school->id, 'amount_cents' => 25000000,
            'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);
        $this->invoice->syncStatus();
    }

    private function tokenFor(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('t')->plainTextToken;
    }

    public function test_correcting_the_amount_reopens_the_invoice(): void
    {
        $this->assertSame('paid', $this->invoice->fresh()->status->value);

        $this->withToken($this->tokenFor($this->accountant))
            ->putJson("/api/payments/{$this->payment->id}", ['amount_cents' => 10000000])
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id, 'amount_cents' => 10000000,
        ]);
        // The balance moved, so the status must move with it.
        $this->assertSame('partial', $this->invoice->fresh()->status->value);
    }

    public function test_method_reference_and_date_can_be_corrected(): void
    {
        $this->withToken($this->tokenFor($this->accountant))
            ->putJson("/api/payments/{$this->payment->id}", [
                'method' => 'mpesa',
                'reference_number' => 'QWE123',
                'paid_at' => '2026-08-20',
            ])->assertOk();

        $fresh = $this->payment->fresh();
        $this->assertSame('QWE123', $fresh->reference_number);
        $this->assertSame('2026-08-20', $fresh->paid_at->toDateString());
    }

    public function test_an_accountant_cannot_reverse_a_payment(): void
    {
        $this->withToken($this->tokenFor($this->accountant))
            ->deleteJson("/api/payments/{$this->payment->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('payments', ['id' => $this->payment->id, 'deleted_at' => null]);
    }

    public function test_a_superadmin_reverses_a_payment_and_the_invoice_reopens(): void
    {
        $admin = User::factory()->create(['school_id' => $this->invoice->school_id]);
        $admin->assignRole('superadmin');

        $this->withToken($this->tokenFor($admin))
            ->deleteJson("/api/payments/{$this->payment->id}")
            ->assertOk();

        // Soft-deleted, not erased: a receipt for it exists somewhere.
        $this->assertSoftDeleted('payments', ['id' => $this->payment->id]);
        $this->assertSame('unpaid', $this->invoice->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.reversed', 'user_id' => $admin->id,
        ]);
    }
}
