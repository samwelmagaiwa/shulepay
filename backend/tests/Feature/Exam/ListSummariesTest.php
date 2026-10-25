<?php

namespace Tests\Feature\Exam;

use App\Models\Employee;
use App\Models\PettyCash;
use App\Models\User;

/**
 * The lists of the finance pages (employees, petty cash) carry totals of everything the filters match, and the
 * filters the screens offer (search, status) really apply.
 */
class ListSummariesTest extends ExamTestCase
{
    public function test_the_employee_list_searches_filters_and_totals_every_page(): void
    {
        foreach ([['E1', 'Asha Mwinyi', 'active', 500000], ['E2', 'Juma Said', 'active', 300000], ['E3', 'Neema Paul', 'terminated', 200000]] as [$no, $name, $status, $pay]) {
            Employee::create(['school_id' => $this->school->id, 'staff_number' => $no, 'full_name' => $name, 'role' => 'Teacher', 'basic_salary_cents' => $pay * 100, 'hire_date' => now()->toDateString(), 'status' => $status]);
        }
        $this->actingAsRole('admin');

        $all = $this->getJson('/api/employees?per_page=5')->assertOk();
        $this->assertSame(['total' => 3, 'active' => 2, 'not_working' => 1, 'monthly_basic_cents' => 80000000], $all->json('summary'));

        $this->assertSame(['Asha Mwinyi'], array_column($this->getJson('/api/employees?search=asha')->json('data'), 'full_name'));
        $this->assertSame(['Neema Paul'], array_column($this->getJson('/api/employees?status=inactive')->json('data'), 'full_name'));
        $this->assertSame(2, $this->getJson('/api/employees?status=active')->json('summary.active'));
    }

    public function test_petty_cash_reports_the_whole_fund_not_only_the_page(): void
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $row = fn (string $type, int $tzs, int $balance, string $day) => PettyCash::create([
            'school_id' => $this->school->id, 'type' => $type, 'amount_cents' => $tzs * 100, 'balance_after_cents' => $balance * 100,
            'description' => $type, 'entry_date' => $day, 'recorded_by' => $user->id,
        ]);
        $row('top_up', 50000, 50000, '2026-10-01');
        $row('expense', 12000, 38000, '2026-10-02');
        $row('expense', 8000, 30000, '2026-10-03');
        $this->actingAsRole('admin');

        $r = $this->getJson('/api/petty-cash?per_page=5')->assertOk();
        $this->assertSame(['balance_cents' => 3000000, 'in_cents' => 5000000, 'out_cents' => 2000000], $r->json('summary'));
        $this->assertCount(3, $r->json('data'));
        $this->assertSame(3000000, $r->json('data.0.balance_after_cents'), 'newest entry first, with its running balance');
    }
}
