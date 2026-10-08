<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\School;
use App\Models\SchoolNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Messages the server writes — "Student deleted.", validation errors, refusals —
 * appear in the app's alerts and toasts, so they have to follow the chosen
 * language too. English text is the source; lang/sw.json holds the Swahili.
 */
class MessageTranslationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary']);
        $this->accountant = User::factory()->create(['school_id' => $this->school->id]);
        $this->accountant->assignRole('accountant');
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    /** @return array<string, string> English source text => file it is used in */
    private function messagesUsedInCode(): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/\b__\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', file_get_contents($file->getPathname()), $m, PREG_SET_ORDER);

            foreach ($m as $hit) {
                $text = $hit[1] === "'"
                    ? str_replace(["\\'", '\\\\'], ["'", '\\'], $hit[2])
                    : str_replace(['\\"', '\\\\'], ['"', '\\'], $hit[2]);

                // "pdf.receipt_no" style keys live in the lang/*/*.php files, not the JSON.
                if (preg_match('/^[a-z_]+(\.[A-Za-z0-9_*.]+)+$/', $text)) {
                    continue;
                }

                $found[$text] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        return $found;
    }

    public function test_every_message_in_the_code_has_a_swahili_translation(): void
    {
        $sw = json_decode(file_get_contents(base_path('lang/sw.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($this->messagesUsedInCode());

        foreach ($this->messagesUsedInCode() as $text => $file) {
            $this->assertArrayHasKey($text, $sw, "Add a Swahili translation to lang/sw.json for: \"{$text}\" ({$file})");
        }
    }

    public function test_translations_keep_the_same_placeholders_and_none_are_unused(): void
    {
        $sw = json_decode(file_get_contents(base_path('lang/sw.json')), true, 512, JSON_THROW_ON_ERROR);
        $used = $this->messagesUsedInCode();

        foreach ($sw as $en => $translated) {
            preg_match_all('/:[a-z_]+/', $en, $a);
            preg_match_all('/:[a-z_]+/', $translated, $b);

            $this->assertEqualsCanonicalizing($a[0], $b[0], "Placeholders differ for: \"{$en}\"");
            $this->assertNotSame($en, $translated, "Not actually translated: \"{$en}\"");
            $this->assertArrayHasKey($en, $used, "Unused translation (the code no longer says this): \"{$en}\"");
        }
    }

    public function test_a_success_message_follows_the_language(): void
    {
        $category = fn () => ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);
        $token = $this->token();

        $en = $this->withToken($token)->withHeader('X-Lang', 'en')->deleteJson("/api/expense-categories/{$category()->id}");
        $sw = $this->withToken($token)->withHeader('X-Lang', 'sw')->deleteJson("/api/expense-categories/{$category()->id}");

        $this->assertSame('Category deleted.', $en->json('message'));
        $this->assertSame('Kategoria imefutwa.', $sw->json('message'));
    }

    public function test_a_message_with_values_in_it_is_translated_with_those_values(): void
    {
        $category = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);
        Expense::create([
            'school_id' => $this->school->id, 'category_id' => $category->id, 'amount_cents' => 1000,
            'description' => 'x', 'expense_date' => '2026-08-31', 'recorded_by' => $this->accountant->id, 'status' => 'pending',
        ]);
        $token = $this->token();

        $sw = $this->withToken($token)->withHeader('X-Lang', 'sw')->deleteJson("/api/expense-categories/{$category->id}")->assertStatus(422);

        $this->assertStringContainsString('matumizi 1', $sw->json('message'));
        $this->assertStringNotContainsString(':count', $sw->json('message'));
    }

    public function test_the_requests_own_language_wins_over_the_default(): void
    {
        // Same endpoint, no language header: the application default (English).
        $category = ExpenseCategory::create(['school_id' => $this->school->id, 'name' => 'Fuel', 'type' => 'operational']);

        $res = $this->withToken($this->token())->deleteJson("/api/expense-categories/{$category->id}");

        $this->assertSame('Category deleted.', $res->json('message'));
    }

    public function test_validation_errors_follow_the_language(): void
    {
        $token = $this->token();

        $en = $this->withToken($token)->withHeader('X-Lang', 'en')->postJson('/api/payments', []);
        $sw = $this->withToken($token)->withHeader('X-Lang', 'sw')->postJson('/api/payments', []);

        $en->assertStatus(422);
        $sw->assertStatus(422);

        $this->assertStringContainsString('is required', $en->json('errors.invoice_id.0'));
        $this->assertStringContainsString('inahitajika', $sw->json('errors.invoice_id.0'));
    }

    public function test_a_form_request_message_follows_the_language(): void
    {
        $token = $this->token();

        $en = $this->withToken($token)->withHeader('X-Lang', 'en')->postJson('/api/students/register', []);
        $sw = $this->withToken($token)->withHeader('X-Lang', 'sw')->postJson('/api/students/register', []);

        $this->assertSame('First name is required.', $en->json('errors.first_name.0'));
        $this->assertSame('Jina la kwanza linahitajika.', $sw->json('errors.first_name.0'));
    }

    public function test_validation_files_cover_every_rule_in_both_languages(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $sw = require base_path('lang/sw/validation.php');

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($sw))), 'lang/sw/validation.php is missing rules');
    }

    public function test_an_absence_alert_is_worded_in_the_readers_language(): void
    {
        $note = SchoolNotification::create([
            'school_id' => $this->school->id, 'type' => 'absence_alert', 'title' => 'stored title', 'body' => 'stored body',
            'data' => ['class_id' => 1, 'class_name' => 'Std 1', 'date' => '2026-10-07', 'absent_count' => 2, 'absent_names' => 'Juma, Asha'],
            'recipient_role' => 'owner', 'is_read' => false,
        ]);

        app()->setLocale('en');
        $this->assertSame('2 students were absent - Std 1', $note->fresh()->title);
        $this->assertSame('Date 2026-10-07: Juma, Asha', $note->fresh()->body);

        app()->setLocale('sw');
        $this->assertSame('Wanafunzi 2 hawakuhudhuria - Std 1', $note->fresh()->title);
        $this->assertSame('Tarehe 2026-10-07: Juma, Asha', $note->fresh()->body);
    }

    public function test_an_older_notification_keeps_its_stored_text(): void
    {
        $old = SchoolNotification::create([
            'school_id' => $this->school->id, 'type' => 'absence_alert', 'title' => 'Wanafunzi 1 hawakuhudhuria - Darasa 1',
            'body' => 'Tarehe 2026-01-01: Neema', 'data' => ['class_id' => 1, 'date' => '2026-01-01', 'absent_count' => 1],
            'recipient_role' => 'owner', 'is_read' => false,
        ]);

        app()->setLocale('en');

        $this->assertSame('Wanafunzi 1 hawakuhudhuria - Darasa 1', $old->fresh()->title);
    }
}
