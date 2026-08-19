<?php
namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Receipt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;
    private Student $student;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'accountant', 'guard_name' => 'web']);

        $school = School::create(['name' => 'Test School', 'code' => 'MSG-01', 'slug' => 'test-school', 'level' => 'primary', 'is_active' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Darasa la 1', 'sort_order' => 1]);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true]);
        $this->student = Student::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'admission_number' => 'M-001', 'first_name' => 'Test', 'last_name' => 'Student', 'status' => 'active', 'admission_date' => '2024-01-15']);

        $structure = FeeStructure::create(['school_id' => $school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Test Structure', 'is_active' => true]);
        $feeItem = FeeItem::create(['fee_structure_id' => $structure->id, 'name' => 'Ada', 'category' => 'masomo', 'amount_cents' => 100000, 'is_optional' => false, 'sort_order' => 0]);

        $this->accountant = User::factory()->create(['school_id' => $school->id]);
        $this->accountant->assignRole('accountant');

        $receipt = Receipt::create(['receipt_number' => 'RCP-0001', 'student_id' => $this->student->id, 'issued_at' => now()]);
        $this->invoice = Invoice::create(['school_id' => $school->id, 'student_id' => $this->student->id, 'term_id' => $term->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-0001', 'total_amount_cents' => 100000, 'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(), 'generated_by' => $this->accountant->id]);
        InvoiceLine::create(['invoice_id' => $this->invoice->id, 'fee_item_id' => $feeItem->id, 'description' => 'Ada', 'amount_cents' => 100000]);
    }

    public function test_balance_is_derived_not_stored(): void
    {
        $this->assertEquals(100000, $this->invoice->balanceDueCents());
        $this->assertEquals(0, $this->invoice->paidCents());
    }

    public function test_payment_reduces_balance(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $response = $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'amount_cents' => 60000,
            'method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->invoice->refresh();
        $this->assertEquals(60000, $this->invoice->paidCents());
        $this->assertEquals(40000, $this->invoice->balanceDueCents());
        $this->assertEquals('partial', $this->invoice->status->value);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'amount_cents' => 100000,
            'method' => 'mpesa',
            'reference_number' => 'MP123',
        ])->assertStatus(201);

        $this->invoice->refresh();
        $this->assertEquals('paid', $this->invoice->status->value);
        $this->assertEquals(0, $this->invoice->balanceDueCents());
    }

    public function test_receipt_is_issued_with_payment(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $response = $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'amount_cents' => 50000,
            'method' => 'bank',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertNotEmpty($data['receipt']['receipt_number']);
        $this->assertStringStartsWith('RCP-', $data['receipt']['receipt_number']);
    }

    public function test_unauthenticated_cannot_record_payment(): void
    {
        $this->postJson('/api/payments', ['invoice_id' => $this->invoice->id, 'amount_cents' => 100, 'method' => 'cash'])
            ->assertStatus(401);
    }
}
