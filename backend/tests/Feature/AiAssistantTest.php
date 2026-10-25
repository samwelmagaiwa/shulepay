<?php

namespace Tests\Feature;

use App\Ai\AiToolExecutor;
use App\Ai\AiToolRegistry;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The ShuleAI assistant. What matters:
 *  - parents never reach it, and without an API key it says so instead of failing
 *  - the tools a user is offered follow their role
 *  - every tool runs against the real schema (a wrong column name would otherwise only
 *    show up in front of a user) and answers are limited to the user's school
 *  - a tool the user was not offered is refused even if the model asks for it
 */
class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $other;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['accountant', 'owner', 'parent', 'teacher_pri', 'superadmin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->other = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);

        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Darasa la 1', 'sort_order' => 1, 'capacity' => 40]);

        $mine = Student::create(['first_name' => 'Juma', 'last_name' => 'Ali', 'status' => 'active']);
        Enrollment::create(['student_id' => $mine->id, 'school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'admission_number' => 'ALP/001', 'status' => 'active', 'admitted_at' => now()]);
        Invoice::create(['school_id' => $this->school->id, 'student_id' => $mine->id, 'term_id' => $term->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-A1', 'total_amount_cents' => 100000, 'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now()]);
        Attendance::create(['school_id' => $this->school->id, 'student_id' => $mine->id, 'school_class_id' => $class->id, 'date' => now()->toDateString(), 'status' => 'absent']);

        $theirs = Student::create(['first_name' => 'Other', 'last_name' => 'Pupil', 'status' => 'active']);
        $oc = SchoolClass::create(['school_id' => $this->other->id, 'name' => 'Beta class', 'sort_order' => 1]);
        $oy = AcademicYear::create(['school_id' => $this->other->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        Enrollment::create(['student_id' => $theirs->id, 'school_id' => $this->other->id, 'school_class_id' => $oc->id, 'academic_year_id' => $oy->id, 'admission_number' => 'BET/001', 'status' => 'active', 'admitted_at' => now()]);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['school_id' => $this->school->id]);
        $u->assignRole($role);

        return $u;
    }

    private function token(User $u): string
    {
        $this->app['auth']->forgetGuards();

        return $u->createToken('t')->plainTextToken;
    }

    private function runAs(User $u, string $tool, array $args = []): array
    {
        app()->instance('active_school', $this->school);

        return (new AiToolExecutor($u))->execute($tool, $args);
    }

    public function test_a_parent_cannot_use_the_assistant(): void
    {
        $this->withToken($this->token($this->user('parent')))
            ->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => 'hi']]])
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_use_the_assistant(): void
    {
        $this->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => 'hi']]])->assertUnauthorized();
    }

    public function test_without_an_api_key_the_assistant_reports_it_is_not_configured(): void
    {
        config(['services.gemini.key' => null]);

        $this->withToken($this->token($this->user('accountant')))
            ->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => 'hi']]])
            ->assertStatus(503);
    }

    public function test_malformed_messages_are_rejected_before_reaching_the_provider(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake();
        $t = $this->token($this->user('accountant'));

        $this->withToken($t)->postJson('/api/ai/chat', ['messages' => [['role' => 'system', 'content' => 'x']]])->assertStatus(422);
        $this->withToken($t)->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => str_repeat('a', 4001)]]])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_a_plain_answer_is_returned(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Hello Juma']]]]],
            'usageMetadata' => ['totalTokenCount' => 12],
        ])]);

        $this->withToken($this->token($this->user('accountant')))
            ->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => 'hi']]])
            ->assertOk()
            ->assertJsonPath('reply', 'Hello Juma');
    }

    public function test_tools_follow_the_role(): void
    {
        $names = fn (string $role) => array_column(AiToolRegistry::toolsFor($this->user($role)), 'name');

        $this->assertContains('get_payment_summary', $names('accountant'));
        $this->assertContains('get_payroll_summary', $names('owner'));

        $teacher = $names('teacher_pri');
        $this->assertContains('get_student_list', $teacher);
        $this->assertContains('get_attendance_summary', $teacher);
        $this->assertNotContains('get_payment_summary', $teacher);
        $this->assertNotContains('get_payroll_summary', $teacher);
        $this->assertNotContains('get_guardian_list', $teacher);
    }

    public function test_a_tool_the_user_was_not_offered_is_refused(): void
    {
        config(['services.gemini.key' => 'test-key']);
        $calls = 0;
        Http::fake(['generativelanguage.googleapis.com/*' => function () use (&$calls) {
            return Http::response($calls++ === 0
                ? ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => 'get_payroll_summary', 'args' => []]]]]]]]
                : ['candidates' => [['content' => ['parts' => [['text' => 'done']]]]]]);
        }]);

        $this->withToken($this->token($this->user('teacher_pri')))
            ->postJson('/api/ai/chat', ['messages' => [['role' => 'user', 'content' => 'salaries?']]])
            ->assertOk()->assertJsonPath('reply', 'done');

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'You do not have access to this information.'));
    }

    public function test_every_tool_runs_against_the_real_schema(): void
    {
        $owner = $this->user('owner');

        foreach (AiToolRegistry::toolsFor($owner) as $tool) {
            $args = match ($tool['name']) {
                'get_student_attendance', 'get_student_financial_summary' => ['student_name' => 'Juma Ali'],
                default => [],
            };
            $result = $this->runAs($owner, $tool['name'], $args);

            $this->assertArrayNotHasKey('error', $result, "{$tool['name']} failed: ".json_encode($result));
        }
    }

    public function test_answers_stay_inside_the_users_school(): void
    {
        $owner = $this->user('owner');

        $students = $this->runAs($owner, 'get_student_list', ['limit' => 50])['students'];
        $this->assertSame(['Juma Ali'], array_column($students, 'name'));

        $this->assertSame(1, $this->runAs($owner, 'get_invoice_stats')['total_invoices']);
        $this->assertSame('Alpha', $this->runAs($owner, 'get_school_overview')['school_name']);
    }

    public function test_all_schools_mode_still_only_covers_the_schools_the_user_may_access(): void
    {
        $accountant = $this->user('accountant');   // belongs to Alpha only
        app()->forgetInstance('active_school');

        $exec = new AiToolExecutor($accountant);
        $this->assertSame(['Juma Ali'], array_column($exec->execute('get_student_list', ['limit' => 50])['students'], 'name'));
        $this->assertSame(['Darasa la 1'], array_column($exec->execute('get_class_list', [])['classes'], 'name'));
        $this->assertSame(1, $exec->execute('get_student_stats', [])['total_enrolled']);

        // Superadmin sees every school.
        $super = $this->user('superadmin');
        $all = array_column((new AiToolExecutor($super))->execute('get_student_list', ['limit' => 50])['students'], 'name');
        sort($all);
        $this->assertSame(['Juma Ali', 'Other Pupil'], $all);
    }

    public function test_invoice_stats_count_payments_on_every_invoice(): void
    {
        $owner = $this->user('owner');
        $first = Invoice::where('invoice_number', 'INV-A1')->first();
        $term2 = Term::create(['academic_year_id' => $first->academic_year_id, 'name' => 'Muhula 2', 'number' => 2, 'start_date' => '2026-05-01', 'end_date' => '2026-08-31', 'is_current' => false]);
        $second = Invoice::create([
            'school_id' => $this->school->id, 'student_id' => $first->student_id, 'term_id' => $term2->id,
            'academic_year_id' => $first->academic_year_id, 'invoice_number' => 'INV-A2', 'total_amount_cents' => 100000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'partial', 'generated_at' => now(),
        ]);
        foreach ([[$first, 30000], [$second, 20000]] as [$inv, $cents]) {
            Payment::create([
                'invoice_id' => $inv->id, 'student_id' => $inv->student_id, 'school_id' => $this->school->id,
                'amount_cents' => $cents, 'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $owner->id,
            ]);
        }

        $stats = $this->runAs($owner, 'get_invoice_stats');

        $this->assertSame(2, $stats['total_invoices']);
        $this->assertSame('TZS 2,000', $stats['total_invoiced']);
        $this->assertSame('TZS 500', $stats['total_collected']);
        $this->assertSame('TZS 1,500', $stats['total_outstanding']);
    }

    public function test_lookups_by_name_and_attendance(): void
    {
        $owner = $this->user('owner');

        $fin = $this->runAs($owner, 'get_student_financial_summary', ['student_name' => 'juma ali']);
        $this->assertSame('TZS 1,000', $fin['students'][0]['outstanding_balance']);

        $att = $this->runAs($owner, 'get_attendance_absentees');
        $this->assertSame('Juma Ali', $att['absent'][0]['name']);

        $this->assertSame(1, $this->runAs($owner, 'get_attendance_summary')['absent']);
    }

    public function test_an_unknown_tool_name_is_an_error_not_a_crash(): void
    {
        $this->assertArrayHasKey('error', $this->runAs($this->user('owner'), 'drop_everything'));
    }
}
