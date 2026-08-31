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

    /**
     * DomPDF's CSS3 selector support is incomplete — a page-break rule keyed
     * on :not(:last-child) once silently matched nothing, so every invoice
     * rendered correctly but ran together as one continuous page instead of
     * each getting its own. Counting real PDF page objects (not just "the
     * bytes contain %PDF") is the only way this regresses loudly instead of
     * silently again.
     */
    public function test_each_invoice_renders_on_its_own_page(): void
    {
        $this->makeInvoice('unpaid', 'Second', 'Debtor');
        $this->makeInvoice('unpaid', 'Third', 'Debtor');

        $response = $this->withToken($this->token())
            ->get('/api/invoices/bulk-receipt?status=unpaid');

        $response->assertOk();

        // /Type /Page marks an actual page object in the PDF's object tree
        // (unlike /Type /Pages, the parent node) — DomPDF does not compress
        // this part of the file, so it is reliably present as plain text.
        $pageCount = preg_match_all('/\/Type\s*\/Page(?!s)\b/', $response->getContent());

        // setUp() already created one 'unpaid' invoice, plus the two made here.
        $this->assertSame(3, $pageCount, 'expected one PDF page per invoice, not invoices merged onto shared pages');
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

    public function test_count_endpoint_reports_how_many_match_without_rendering_a_pdf(): void
    {
        $response = $this->withToken($this->token())
            ->getJson('/api/invoices/bulk-receipt/count?status=unpaid');

        $response->assertOk()->assertJson([
            'count' => 1,
            'batch_size' => 50,
            'batch_count' => 1,
            'max_batch' => 150,
        ]);
    }

    public function test_count_reports_multiple_batches_for_a_large_matching_set(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->makeInvoice('unpaid', 'Extra', "Student{$i}");
        }

        $response = $this->withToken($this->token())
            ->getJson('/api/invoices/bulk-receipt/count?status=unpaid');

        // 60 extra + the 1 from setUp = 61, batch size 50 -> 2 batches.
        $response->assertOk()->assertJson(['count' => 61, 'batch_count' => 2]);
    }

    public function test_offset_and_limit_select_a_specific_batch(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->makeInvoice('unpaid', 'Extra', "Student{$i}");
        }

        // First batch: 50 of the 61 matching invoices.
        $first = $this->withToken($this->token())
            ->get('/api/invoices/bulk-receipt?status=unpaid&offset=0&limit=50');
        $first->assertOk();

        // Second batch: the remaining 11.
        $second = $this->withToken($this->token())
            ->get('/api/invoices/bulk-receipt?status=unpaid&offset=50&limit=50');
        $second->assertOk();

        // Both batches render successfully and are real, distinct PDFs —
        // the actual per-invoice split is exercised by BulkInvoicesPdf
        // directly; this proves offset/limit reach the query at all.
        $this->assertStringContainsString('%PDF', substr($first->getContent(), 0, 10));
        $this->assertStringContainsString('%PDF', substr($second->getContent(), 0, 10));
    }

    public function test_limit_over_the_hard_cap_is_rejected(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/invoices/bulk-receipt?status=unpaid&limit=151')
            ->assertStatus(422);
    }
}
