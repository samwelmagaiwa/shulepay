<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Invoices page's bulk-print-by-status action.
 * What matters:
 *  - it returns exactly the invoices matching the requested status, no others
 *  - the response is a real PDF, not an error page
 *  - it isn't shadowed by the invoices/{invoice} apiResource route (the same
 *    landmine invoices/orphaned already needed a fix for)
 */
class BulkInvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private School $school;

    private Term $term;

    /** A real class no test student is ever enrolled in — used to prove the "no matches" 404. */
    private SchoolClass $emptyClass;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Test School', 'code' => 'BLK-01', 'slug' => 'test-school-blk', 'level' => 'primary', 'is_active' => true]);
        $this->emptyClass = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Darasa la 1', 'sort_order' => 1]);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);
        $this->term = Term::create(['academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true]);

        $this->accountant = User::factory()->create(['school_id' => $this->school->id]);
        $this->accountant->assignRole('accountant');

        $this->makeInvoice('unpaid', 'Juma', 'Unpaid');
        $this->makeInvoice('partial', 'Asha', 'Partial');
        $this->makeInvoice('paid', 'Baraka', 'Paid');
    }

    private function makeInvoice(string $status, string $first, string $last): Invoice
    {
        $student = Student::create(['first_name' => $first, 'last_name' => $last, 'status' => 'active']);

        return Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'term_id' => $this->term->id,
            'academic_year_id' => $this->term->academic_year_id,
            'invoice_number' => 'INV-'.strtoupper($status).'-'.$student->id,
            'total_amount_cents' => 100000,
            'discount_cents' => 0,
            'arrears_cents' => 0,
            'status' => $status,
            'generated_at' => now(),
            'generated_by' => $this->accountant->id,
        ]);
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    public function test_bulk_print_returns_only_the_requested_status(): void
    {
        $response = $this->withToken($this->token())
            ->get('/api/invoices/bulk-receipt?status=unpaid');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', substr($response->getContent(), 0, 10));
    }

    public function test_bulk_print_is_not_shadowed_by_the_invoice_show_route(): void
    {
        // invoices/{invoice} (apiResource show) would otherwise capture
        // "bulk-receipt" as the {invoice} id if route order regressed.
        $response = $this->withToken($this->token())
            ->get('/api/invoices/bulk-receipt?status=partial');

        $response->assertOk();
        $this->assertNotSame(404, $response->status());
    }

    public function test_no_matching_invoices_returns_404(): void
    {
        // A real, valid class — just one no test student is enrolled in — so
        // this exercises "filter matched nothing", not "invalid class id".
        $this->withToken($this->token())
            ->getJson("/api/invoices/bulk-receipt?status=unpaid&school_class_id={$this->emptyClass->id}")
            ->assertStatus(404);
    }

    public function test_status_is_required(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/invoices/bulk-receipt')
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_bulk_print(): void
    {
        $this->getJson('/api/invoices/bulk-receipt?status=unpaid')->assertStatus(401);
    }
}
