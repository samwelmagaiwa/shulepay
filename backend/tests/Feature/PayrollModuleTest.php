<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Payroll: employees, a month generated for everyone with the statutory deductions worked out,
 * payslip / summary / annual PDFs, and each staff member seeing only their own payslips.
 */
class PayrollModuleTest extends TestCase
{
    use RefreshDatabase;

    private School $mine;

    private School $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['owner', 'accountant', 'teacher_pri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->mine = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->theirs = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
    }

    private function as(string $role, ?User $user = null): static
    {
        if (! $user) {
            $user = User::factory()->create(['school_id' => $this->mine->id]);
            $user->assignRole($role);
        }
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('t')->plainTextToken);
    }

    private function employee(School $school, string $no, string $name, int $salaryTzs, ?int $userId = null): Employee
    {
        return Employee::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'user_id' => $userId, 'staff_number' => $no, 'full_name' => $name, 'role' => 'Teacher',
            'basic_salary_cents' => $salaryTzs * 100, 'hire_date' => '2025-01-01', 'status' => 'active',
        ]);
    }

    public function test_a_month_is_generated_for_every_active_employee_with_statutory_deductions(): void
    {
        $this->employee($this->mine, 'T1', 'Asha Mwinyi', 1_500_000);
        $this->employee($this->mine, 'T2', 'Juma Said', 600_000);
        $this->employee($this->theirs, 'B1', 'Beta Teacher', 900_000);

        $r = $this->as('accountant')->postJson('/api/payroll/bulk-generate', ['month' => 10, 'year' => 2026])->assertOk();
        $this->assertEqualsCanonicalizing(['T1', 'T2'], $r->json('created'), "only this school's staff");

        $row = Payroll::withoutGlobalScopes()->where('employee_id', Employee::withoutGlobalScopes()->where('staff_number', 'T1')->value('id'))->first();
        $basic = (int) $row->getRawOriginal('basic_salary_cents');
        $this->assertSame(150_000_000, $basic);
        $this->assertGreaterThan(0, (int) $row->getRawOriginal('paye_cents'), 'PAYE is charged on a TZS 1.5m salary');
        $this->assertSame(15_000_000, (int) $row->getRawOriginal('nssf_employee_cents'), 'NSSF employee share is 10%');
        $this->assertSame(
            $basic - (int) $row->getRawOriginal('deductions_cents'),
            (int) $row->getRawOriginal('net_salary_cents'),
            'net pay = gross − the employee deductions',
        );

        // generating the same month again does not duplicate anyone
        $again = $this->as('accountant')->postJson('/api/payroll/bulk-generate', ['month' => 10, 'year' => 2026])->assertOk();
        $this->assertSame([], $again->json('created'));
        $this->assertCount(2, Payroll::withoutGlobalScopes()->where('month', 10)->get());
    }

    public function test_the_payslip_summary_and_annual_statement_print(): void
    {
        $emp = $this->employee($this->mine, 'T1', 'Asha Mwinyi', 1_500_000);
        $this->as('accountant')->postJson('/api/payroll/bulk-generate', ['month' => 10, 'year' => 2026])->assertOk();
        $payroll = Payroll::withoutGlobalScopes()->where('employee_id', $emp->id)->first();

        foreach ([
            "/api/payroll/{$payroll->id}/slip",
            '/api/payroll/report/monthly?month=10&year=2026',
            "/api/payroll/report/annual/{$emp->id}?year=2026",
        ] as $url) {
            $res = $this->as('accountant')->get($url);
            $res->assertOk();
            $this->assertStringContainsString('%PDF', substr($res->getContent(), 0, 10), $url);
        }
    }

    public function test_a_staff_member_sees_only_their_own_payslips(): void
    {
        $teacher = User::factory()->create(['school_id' => $this->mine->id]);
        $teacher->assignRole('teacher_pri');
        $me = $this->employee($this->mine, 'T1', 'Asha Mwinyi', 800_000, $teacher->id);
        $other = $this->employee($this->mine, 'T2', 'Juma Said', 900_000);
        $this->as('accountant')->postJson('/api/payroll/bulk-generate', ['month' => 10, 'year' => 2026])->assertOk();

        $mine = Payroll::withoutGlobalScopes()->where('employee_id', $me->id)->first();
        $theirs = Payroll::withoutGlobalScopes()->where('employee_id', $other->id)->first();

        $list = $this->as('teacher_pri', $teacher)->getJson('/api/my-payroll')->assertOk()->json('data');
        $this->assertSame([$mine->id], array_column($list, 'id'));
        $this->as('teacher_pri', $teacher)->get("/api/my-payroll/{$mine->id}/slip")->assertOk();
        $this->as('teacher_pri', $teacher)->getJson("/api/my-payroll/{$theirs->id}")->assertForbidden();

        // and a teacher has no way into the payroll itself
        $this->as('teacher_pri', $teacher)->getJson('/api/payroll')->assertForbidden();
        $this->as('teacher_pri', $teacher)->postJson('/api/payroll/bulk-generate', ['month' => 11, 'year' => 2026])->assertForbidden();
    }

    public function test_another_schools_payroll_is_out_of_reach(): void
    {
        $foreign = $this->employee($this->theirs, 'B1', 'Beta Teacher', 900_000);
        $row = Payroll::withoutGlobalScopes()->create([
            'school_id' => $this->theirs->id, 'employee_id' => $foreign->id, 'month' => 10, 'year' => 2026,
            'basic_salary_cents' => 90_000_000, 'allowances_cents' => 0, 'deductions_cents' => 0, 'net_salary_cents' => 90_000_000,
            'payment_method' => 'bank', 'status' => 'pending', 'recorded_by' => User::factory()->create(['school_id' => $this->theirs->id])->id,
        ]);

        $this->as('accountant')->get("/api/payroll/{$row->id}/slip")->assertStatus(404);
        $this->as('accountant')->postJson("/api/payroll/{$row->id}/mark-paid")->assertStatus(404);
        $names = collect($this->as('accountant')->withHeaders(['X-School-Id' => '0'])->getJson('/api/payroll')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertNotContains($row->id, $names, '"all schools" mode must not open other schools');
    }
}
