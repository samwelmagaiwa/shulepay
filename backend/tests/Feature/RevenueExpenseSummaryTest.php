<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Reporting\RevenueExpenseSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Revenue and expenses must be measured over the same window, count only money
 * actually received and spending actually approved, and stay inside one school.
 */
class RevenueExpenseSummaryTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $user;

    private Invoice $invoice;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'FIRST TERM', 'number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-03-30', 'is_current' => true,
        ]);
        $this->user = User::factory()->create(['school_id' => $this->school->id]);
        $student = Student::create(['first_name' => 'Juma', 'last_name' => 'Mfungo', 'status' => 'active']);

        $this->invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $this->school->id,
            'term_id' => $term->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'total_amount_cents' => 100000000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'partial',
            'generated_at' => now(), 'generated_by' => $this->user->id,
        ]);
        $this->category = ExpenseCategory::create([
            'school_id' => $this->school->id, 'name' => 'Umeme', 'type' => 'operational',
        ]);
    }

    private function pay(int $cents, string $date, ?int $schoolId = null): void
    {
        Payment::withoutGlobalScope('school')->create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->invoice->student_id,
            'school_id' => $schoolId ?? $this->school->id, 'amount_cents' => $cents,
            'method' => 'cash', 'paid_at' => $date, 'recorded_by' => $this->user->id,
        ]);
    }

    private function spend(int $cents, string $date, string $status = 'approved'): void
    {
        Expense::withoutGlobalScopes()->create([
            'school_id' => $this->school->id, 'category_id' => $this->category->id,
            'amount_cents' => $cents, 'description' => 'Bili',
            'expense_date' => $date, 'recorded_by' => $this->user->id, 'status' => $status,
        ]);
    }

    private function summary(): array
    {
        return app(RevenueExpenseSummary::class)->for($this->school->id, Carbon::parse('2026-09-15'));
    }

    public function test_revenue_expenses_and_net_over_the_academic_year(): void
    {
        $this->pay(30000000, '2026-02-10');
        $this->pay(20000000, '2026-08-01');
        $this->spend(15000000, '2026-03-05');

        $s = $this->summary();

        $this->assertSame(50000000, $s['revenue_cents']);
        $this->assertSame(15000000, $s['expenses_cents']);
        $this->assertSame(35000000, $s['net_cents']);
        $this->assertSame(30.0, $s['expense_ratio']);
        $this->assertSame(['label' => '2026', 'from' => '2026-01-01', 'to' => '2026-12-31'], $s['period']);
    }

    /** Both sides share one window — nothing outside the year leaks into either. */
    public function test_activity_outside_the_period_is_excluded_from_both_sides(): void
    {
        $this->pay(40000000, '2025-12-31');
        $this->spend(9000000, '2027-01-01');

        $s = $this->summary();

        $this->assertSame(0, $s['revenue_cents']);
        $this->assertSame(0, $s['expenses_cents']);
    }

    /** A pending expense may still be rejected, so it is not yet spending. */
    public function test_only_approved_expenses_count(): void
    {
        $this->spend(5000000, '2026-04-01', 'approved');
        $this->spend(7000000, '2026-04-02', 'pending');

        $this->assertSame(5000000, $this->summary()['expenses_cents']);
    }

    public function test_another_schools_payments_are_not_counted(): void
    {
        $other = School::create(['name' => 'Sekondari', 'code' => 'SEK', 'slug' => 'sekondari', 'level' => 'secondary']);
        $this->pay(10000000, '2026-05-01');
        $this->pay(99000000, '2026-05-01', $other->id);

        $this->assertSame(10000000, $this->summary()['revenue_cents']);
    }

    public function test_no_revenue_gives_a_zero_ratio_not_a_division_error(): void
    {
        $this->spend(5000000, '2026-04-01');

        $s = $this->summary();

        $this->assertSame(0.0, $s['expense_ratio']);
        $this->assertSame(-5000000, $s['net_cents']);
    }
}
