<?php

namespace Tests\Feature\Exam;

use App\Ai\AiToolExecutor;
use App\Ai\AiToolRegistry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

/**
 * The expenses list carries its own totals, the owner can reject with a reason, and the assistant reads the same
 * numbers (approved spending only, paid versus credit, what waits for approval).
 */
class ExpenseDataTest extends ExamTestCase
{
    private ExpenseCategory $fuel;

    private ExpenseCategory $food;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fuel = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);
        $this->food = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Food', 'type' => 'operational']);
        $today = now()->toDateString();
        $recorder = User::factory()->create(['school_id' => $this->school->id]);
        $row = fn (ExpenseCategory $c, int $tzs, string $status, array $more = []) => Expense::create($more + [
            'school_id' => $this->school->id, 'category_id' => $c->id, 'amount_cents' => $tzs * 100, 'description' => 'Item '.$tzs,
            'expense_date' => $today, 'recorded_by' => $recorder->id, 'status' => $status, 'payment_mode' => 'paid', 'vendor' => 'Shell',
        ]);
        $row($this->fuel, 10000, 'approved');
        $row($this->fuel, 5000, 'approved', ['payment_mode' => 'credit', 'vendor' => 'Lake Oil', 'receipt_reference' => 'INV-77']);
        $row($this->food, 2000, 'pending');
        $row($this->food, 3000, 'rejected', ['rejection_reason' => 'No receipt']);
    }

    public function test_the_list_carries_totals_for_everything_the_filters_match(): void
    {
        $this->actingAsRole('admin');
        $r = $this->getJson('/api/expenses?per_page=5')->assertOk();
        $s = $r->json('summary');

        $this->assertSame(1500000, $s['approved_cents']);
        $this->assertSame(1000000, $s['paid_cents']);
        $this->assertSame(500000, $s['credit_cents']);
        $this->assertSame(200000, $s['pending_cents']);
        $this->assertSame(1, $s['pending_count']);
        $this->assertSame(1, $s['rejected_count']);
        $this->assertSame(1500000, $s['this_month_cents']);
        $this->assertSame('Fuel', $s['top_categories'][0]['name']);
        $this->assertCount(4, $r->json('data'));

        $this->assertSame(1, count($this->getJson('/api/expenses?search=INV-77')->json('data')));
        $this->assertSame(1, count($this->getJson('/api/expenses?payment_mode=credit')->json('data')));
        $this->assertSame(0, $this->getJson('/api/expenses?payment_mode=credit&status=pending')->json('summary.count'));
    }

    public function test_rejecting_needs_a_reason_and_only_a_pending_expense(): void
    {
        $this->actingAsRole('admin');
        $pending = Expense::where('status', 'pending')->first();

        $this->postJson("/api/expenses/{$pending->id}/reject", ['reason' => 'no'])->assertStatus(422);
        $this->postJson("/api/expenses/{$pending->id}/reject", ['reason' => 'Not budgeted for this term'])
            ->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('rejection_reason', 'Not budgeted for this term');

        $this->postJson("/api/expenses/{$pending->id}/reject", ['reason' => 'Again and again'])->assertStatus(422);
        $this->postJson('/api/expenses/'.Expense::where('status', 'approved')->first()->id.'/approve')->assertStatus(422);
    }

    public function test_the_assistant_counts_only_approved_spending_and_splits_paid_from_credit(): void
    {
        app()->instance('active_school', $this->school);
        $ai = new AiToolExecutor(User::factory()->create(['school_id' => $this->school->id]));
        $range = ['from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->toDateString()];

        $r = $ai->execute('get_expense_summary', $range);
        $this->assertSame('TZS 15,000', $r['grand_total']);
        $this->assertSame('TZS 10,000', $r['paid_now']);
        $this->assertSame('TZS 5,000', $r['on_credit_owed_to_suppliers']);
        $this->assertSame(['count' => 1, 'total' => 'TZS 2,000'], $r['waiting_for_approval']);
        $this->assertSame(1, $r['rejected_count']);
        $this->assertSame('Fuel', $r['by_category'][0]['category']);

        foreach (['vendor' => 'Shell', 'payment_mode' => 'paid', 'month' => now()->format('Y-m')] as $group => $first) {
            $g = $ai->execute('get_expense_summary', $range + ['group_by' => $group]);
            $this->assertArrayNotHasKey('error', $g);
            $this->assertSame($first, $g['breakdown'][0][$group]);
        }
        $this->assertSame('TZS 5,000', $ai->execute('get_expense_summary', $range + ['vendor' => 'Lake'])['grand_total']);
    }

    public function test_the_assistant_lists_the_queue_and_the_rejected_with_reasons(): void
    {
        app()->instance('active_school', $this->school);
        $ai = new AiToolExecutor(User::factory()->create(['school_id' => $this->school->id]));

        $pending = $ai->execute('get_expenses_list', ['status' => 'pending']);
        $this->assertSame(1, $pending['matching']);
        $this->assertSame('TZS 2,000', $pending['total_of_matching']);

        $rejected = $ai->execute('get_expenses_list', ['status' => 'rejected'])['expenses'][0];
        $this->assertSame('No receipt', $rejected['rejection_reason']);

        $credit = $ai->execute('get_expenses_list', ['payment_mode' => 'credit', 'search' => 'INV-77']);
        $this->assertSame(1, $credit['matching']);
        $this->assertSame('on credit', $credit['expenses'][0]['payment']);

        $names = array_column(AiToolRegistry::toolsFor(User::factory()->create(['school_id' => $this->school->id])->assignRole('owner')), 'name');
        $this->assertContains('get_expenses_list', $names);
    }
}
