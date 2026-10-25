<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every report the Reports page offers must open as JSON and export as PDF and Excel — also for a school with
 * nothing recorded yet. A missing template only shows when somebody presses Export PDF, so each one is pressed here.
 */
class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['owner', 'accountant', 'teacher_pri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
    }

    private function as(string $role): static
    {
        $u = User::factory()->create(['school_id' => $this->school->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    /** @return array<string, string> report type => query */
    public static function reports(): array
    {
        $day = now()->toDateString();
        $from = now()->startOfMonth()->toDateString();

        return [
            'collections' => ['collections', "from={$from}&to={$day}"],
            'debtor-aging' => ['debtor-aging', ''],
            'by-class' => ['by-class', ''],
            'vs' => ['vs', 'year='.now()->year],
            'income-statement' => ['income-statement', "from={$from}&to={$day}"],
            'balance-sheet' => ['balance-sheet', "as_of={$day}"],
            'cash-flow' => ['cash-flow', "from={$from}&to={$day}"],
            'outstanding-debts' => ['outstanding-debts', ''],
        ];
    }

    /** @dataProvider reports */
    public function test_each_report_exports_as_pdf_and_excel(string $type, string $query): void
    {
        $pdf = $this->as('accountant')->get("/api/reports/{$type}/pdf?{$query}");
        $pdf->assertOk();
        $this->assertStringContainsString('%PDF', substr($pdf->getContent(), 0, 10), "{$type} PDF");

        $xlsx = $this->as('accountant')->get("/api/reports/{$type}/xlsx?{$query}");
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsx->headers->get('Content-Type'), "{$type} Excel is a workbook");
    }

    public function test_the_financial_statements_open_as_json(): void
    {
        $day = now()->toDateString();
        $from = now()->startOfMonth()->toDateString();
        $this->as('owner')->getJson("/api/reports/income-statement?from={$from}&to={$day}")->assertOk();
        $this->as('owner')->getJson("/api/reports/balance-sheet?as_of={$day}")->assertOk();
        $this->as('owner')->getJson("/api/reports/cash-flow?from={$from}&to={$day}")->assertOk();
    }

    public function test_a_teacher_cannot_export_reports(): void
    {
        $this->as('teacher_pri')->get('/api/reports/cash-flow/pdf')->assertForbidden();
    }
}
