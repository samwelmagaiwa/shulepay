<?php

namespace Tests\Feature\Exam;

use App\Ai\AiToolExecutor;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * Budget vs actual: the actual of a line follows the approved expenses of its category inside the budget's year;
 * the owner is warned before a line goes over; and only the owner can delete spending.
 */
class BudgetActualsTest extends ExamTestCase
{
    private ExpenseCategory $fuel;

    private ExpenseCategory $food;

    private Budget $budget;

    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maker = User::factory()->create(['school_id' => $this->school->id]);
        $this->fuel = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);
        $this->food = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Food', 'type' => 'operational']);

        $this->budget = Budget::create(['school_id' => $this->school->id, 'academic_year_id' => $this->year->id, 'name' => 'Budget 2026', 'status' => 'active']);
        BudgetItem::create(['budget_id' => $this->budget->id, 'category' => 'Fuel', 'description' => 'School bus fuel', 'planned_cents' => 100_000_00, 'actual_cents' => 0, 'kind' => 'expense', 'expense_category_id' => $this->fuel->id]);
        BudgetItem::create(['budget_id' => $this->budget->id, 'category' => 'Food', 'description' => 'Kitchen', 'planned_cents' => 50_000_00, 'actual_cents' => 0, 'kind' => 'expense', 'expense_category_id' => $this->food->id]);
        BudgetItem::create(['budget_id' => $this->budget->id, 'category' => 'Other', 'description' => 'Typed by hand', 'planned_cents' => 20_000_00, 'actual_cents' => 7_000_00, 'kind' => 'expense']);
    }

    private function expense(ExpenseCategory $cat, int $tzs, string $status, string $date = '2026-05-10'): Expense
    {
        return Expense::create([
            'school_id' => $this->school->id, 'category_id' => $cat->id, 'amount_cents' => $tzs * 100, 'description' => 'Item '.$tzs,
            'expense_date' => $date, 'recorded_by' => $this->maker->id, 'status' => $status, 'payment_mode' => 'paid',
        ]);
    }

    private function line(array $summary, string $category): array
    {
        return collect($summary)->firstWhere('category', $category);
    }

    public function test_the_actual_is_the_approved_spending_of_the_category_inside_the_year(): void
    {
        $this->expense($this->fuel, 60_000, 'approved');
        $this->expense($this->fuel, 15_000, 'approved', '2026-11-02');
        $this->expense($this->fuel, 9_000, 'pending');                       // not spent yet
        $this->expense($this->fuel, 8_000, 'rejected');                      // never spent
        $this->expense($this->fuel, 70_000, 'approved', '2025-12-31');       // another year
        $this->expense($this->food, 52_000, 'approved');

        $this->actingAsRole('admin');
        $data = $this->getJson("/api/budgets/{$this->budget->id}")->assertOk()->json();

        $fuel = $this->line($data['items'], 'Fuel');
        $this->assertSame(10_000_000, $fuel['planned_cents']);
        $this->assertSame(7_500_000, $fuel['actual_cents']);
        $this->assertSame(2_500_000, $fuel['remaining_cents']);
        $this->assertSame(75, $fuel['percent']);
        $this->assertFalse($fuel['over']);
        $this->assertSame('expenses', $fuel['source']);

        $food = $this->line($data['items'], 'Food');
        $this->assertTrue($food['over'], '52,000 spent of 50,000 planned');

        $other = $this->line($data['items'], 'Other');
        $this->assertSame(700_000, $other['actual_cents'], 'a line linked to nothing keeps its typed actual');
        $this->assertSame('manual', $other['source']);

        $this->assertSame(17_000_000, $data['summary']['planned_cents']);
        $this->assertSame(7_500_000 + 5_200_000 + 700_000, $data['summary']['actual_cents']);
        $this->assertSame(1, $data['summary']['over_count']);
    }

    public function test_the_owner_is_warned_before_a_line_goes_over(): void
    {
        $this->expense($this->fuel, 95_000, 'approved');
        $this->actingAsRole('accountant');

        $ok = $this->postJson('/api/expenses', ['category_id' => $this->fuel->id, 'amount_cents' => 4_000_00, 'description' => 'Small top-up', 'expense_date' => '2026-06-01'])->assertCreated();
        $this->assertNull($ok->json('budget_warning'));

        $over = $this->postJson('/api/expenses', ['category_id' => $this->fuel->id, 'amount_cents' => 8_000_00, 'description' => 'Big top-up', 'expense_date' => '2026-06-02'])->assertCreated();
        $this->assertSame(300_000, $over->json('budget_warning.over_cents'));
        $this->assertSame('Fuel', $over->json('budget_warning.category'));
    }

    public function test_only_the_owner_can_delete_spending_and_an_approved_one_needs_a_reason(): void
    {
        $pending = $this->expense($this->fuel, 1_000, 'pending');
        $approved = $this->expense($this->fuel, 2_000, 'approved');

        $this->actingAsRole('accountant');
        $this->deleteJson("/api/expenses/{$pending->id}")->assertForbidden();
        $this->assertDatabaseHas('expenses', ['id' => $pending->id]);

        $this->actingAsRole('admin');
        $this->deleteJson("/api/expenses/{$pending->id}")->assertOk();
        $this->deleteJson("/api/expenses/{$approved->id}")->assertStatus(422);
        $this->deleteJson("/api/expenses/{$approved->id}", ['reason' => 'Recorded twice'])->assertOk();
        $this->assertDatabaseMissing('expenses', ['id' => $approved->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.deleted']);
    }

    public function test_the_assistant_reads_the_same_live_budget(): void
    {
        $this->expense($this->fuel, 60_000, 'approved');
        app()->instance('active_school', $this->school);
        $r = (new AiToolExecutor(User::factory()->create(['school_id' => $this->school->id])))->execute('get_budget_summary', ['academic_year_id' => $this->year->id]);

        $this->assertArrayNotHasKey('error', $r);
        $this->assertSame('Budget 2026', $r['budget_name']);
        $fuel = collect($r['by_category'])->firstWhere('category', 'Fuel');
        $this->assertSame('TZS 60,000', $fuel['actual']);
        $this->assertSame('60%', $fuel['used']);
    }

    public function test_a_budget_line_can_be_removed_but_not_from_a_closed_budget(): void
    {
        $this->actingAsRole('admin');
        $item = $this->budget->items()->where('category', 'Other')->first();
        $this->deleteJson("/api/budgets/{$this->budget->id}/items/{$item->id}")->assertOk();
        $this->assertDatabaseMissing('budget_items', ['id' => $item->id]);

        $this->budget->update(['status' => 'closed']);
        $fuelItem = $this->budget->items()->where('category', 'Fuel')->first();
        $this->deleteJson("/api/budgets/{$this->budget->id}/items/{$fuelItem->id}")->assertStatus(422);
    }
}
