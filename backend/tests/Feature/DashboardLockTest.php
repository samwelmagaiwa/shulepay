<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DashboardLock;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Services\Reporting\DashboardService;
use App\Support\DashboardPrivacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The dashboard privacy lock. The properties that matter:
 *  - locked figures are ABSENT from the response, not merely hidden client-side
 *  - the lock is per-user: one accountant locking does not affect the owner
 *  - non-money data (headcounts) keeps flowing, so the rest of the page works
 *  - the spreadsheet export cannot be used to read around the lock
 */
class DashboardLockTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->school = School::create([
            'name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary',
        ]);

        // DashboardService builds its trend with MySQL's DATE_FORMAT, so the real
        // stats query cannot run on the SQLite test connection. These tests are
        // about what the controller WITHHOLDS from the payload, not how the
        // payload is computed, so a fixed stub keeps them honest and portable.
        $this->instance(DashboardService::class, new class extends DashboardService
        {
            public function stats(?int $schoolId): array
            {
                return [
                    'total_students' => 123,
                    'sponsored_free_count' => 9,
                    'total_collected_cents' => 6534700000,
                    'total_outstanding_cents' => 3082800000,
                    'today_collections' => 5165900000,
                    'yesterday_collections' => 1368800000,
                    'paid_partial_invoices' => 313,
                    'paid_partial_amount_cents' => 6534700000,
                    'class_fee_breakdown_cents' => ['STD 4' => 2013300000],
                    'paid_amount_cents' => 6534700000,
                    'total_expenses_cents' => 120000000,
                    'collection_rate' => 68.4,
                    'weekly_trend' => [
                        ['date' => '25/08', 'amount' => 1370000000],
                        ['date' => '26/08', 'amount' => 6680000000],
                    ],
                    // payment_trend names its value total_cents, NOT amount —
                    // the mismatch once left these totals exposed under the lock.
                    'payment_trend' => [
                        ['month' => '2026-07', 'total_cents' => 4094000000],
                        ['month' => '2026-08', 'total_cents' => 8188000000],
                    ],
                    'method_breakdown' => [['method' => 'cash', 'count' => 4, 'total' => 990000]],
                    'recent_payments' => [['student' => 'Juma', 'amount_cents' => 500000]],
                    'top_debtors' => [['student' => 'Asha', 'balance' => 700000]],
                    // Not money: these must survive the redaction untouched.
                    'class_breakdown' => ['darasa_la_1' => 30],
                    'school_breakdown' => [['school' => 'Msingi', 'count' => 137]],
                ];
            }
        });
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create([
            'school_id' => $role === 'superadmin' ? null : $this->school->id,
            'password' => Hash::make('secret-password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * The auth guard caches the first user it resolves for the lifetime of the
     * test's application instance, so a second request with a different token
     * would otherwise still be treated as the first user. Production serves each
     * request in its own process and never sees this; forgetting the guards here
     * makes the test behave the way real traffic does.
     */
    private function token(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('t')->plainTextToken;
    }

    public function test_stats_are_unredacted_when_no_lock_is_configured(): void
    {
        $user = $this->userWith('accountant');

        $res = $this->withToken($this->token($user))->getJson('/api/dashboard/stats')->assertOk();

        $this->assertNull($res->json('locked'));
        $this->assertNotNull($res->json('total_outstanding_cents'));
    }

    public function test_locking_withholds_money_keys_from_the_response(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234'])
            ->assertOk()->assertJson(['configured' => true, 'locked' => true]);

        $res = $this->withToken($token)->getJson('/api/dashboard/stats')->assertOk();

        $this->assertTrue($res->json('locked'));
        foreach (DashboardPrivacy::MONEY_KEYS as $key) {
            $this->assertEmpty($res->json($key), "{$key} should be withheld while locked");
        }
    }

    /**
     * The shape the chart draws must carry no recoverable scale: ratios only,
     * relative to the tallest bar, with the amounts themselves gone.
     */
    public function test_trend_shape_is_a_ratio_not_a_scaled_amount(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234']);

        $rows = $this->withToken($token)->getJson('/api/dashboard/stats')->json('weekly_trend');

        // Stub amounts are 1_370_000_000 and 6_680_000_000 — the taller is the
        // reference at 100, the other its true proportion of it.
        $this->assertSame(100.0, (float) $rows[1]['shape']);
        $this->assertEqualsWithDelta(20.51, (float) $rows[0]['shape'], 0.01);
        $this->assertSame('25/08', $rows[0]['date'], 'labels must survive');
    }

    public function test_non_money_data_still_flows_while_locked(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234']);

        $res = $this->withToken($token)->getJson('/api/dashboard/stats')->assertOk();

        // Headcounts are not money; hiding them would break the rest of the page.
        $this->assertArrayHasKey('total_students', $res->json());
        $this->assertNotNull($res->json('total_students'));
        $this->assertNotNull($res->json('sponsored_free_count'));
        $this->assertNotEmpty($res->json('class_breakdown'));
        $this->assertNotEmpty($res->json('school_breakdown'));
    }

    /**
     * The trend charts plot the same amounts as the cards. The final bar of
     * weekly_trend is literally today's collections, so a locked dashboard that
     * still shipped these would be showing in a graph what the card hides.
     */
    public function test_trend_and_breakdown_amounts_are_withheld_too(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234']);

        $res = $this->withToken($token)->getJson('/api/dashboard/stats')->assertOk();

        foreach (['method_breakdown', 'recent_payments', 'top_debtors'] as $key) {
            $this->assertEmpty($res->json($key), "{$key} still carries amounts while locked");
        }

        // The trends keep their shape so the chart still draws, but every row
        // must have lost its amount in favour of a 0-100 ratio.
        foreach (['weekly_trend', 'payment_trend'] as $key) {
            $rows = $res->json($key);
            $this->assertNotEmpty($rows, "{$key} should keep its shape so the chart still draws");
            foreach ($rows as $row) {
                // Every spelling a value could arrive under, not just 'amount' —
                // payment_trend uses total_cents, and checking only 'amount'
                // once let those totals through untouched.
                foreach (['amount', 'total_cents', 'total', 'amount_cents', 'balance', 'cents'] as $valueKey) {
                    $this->assertArrayNotHasKey($valueKey, $row, "{$key} still exposes {$valueKey}");
                }
                $this->assertArrayHasKey('shape', $row, "{$key} lost its shape");
                $this->assertGreaterThanOrEqual(0, $row['shape']);
                $this->assertLessThanOrEqual(100, $row['shape']);
            }
        }
        $this->assertNull($res->json('paid_amount_cents'));
        $this->assertNull($res->json('total_expenses_cents'));
        $this->assertNull($res->json('collection_rate'));

        // No amount from the stub may appear anywhere in the serialised payload.
        $body = $res->getContent();
        foreach ([
            '1370000000', '6680000000', '6534700000', '3082800000', '5165900000',
            '4094000000', '8188000000',
        ] as $amount) {
            $this->assertStringNotContainsString($amount, $body, "amount {$amount} leaked while locked");
        }
    }

    public function test_correct_code_reveals_the_figures_again(): void
    {
        $user = $this->userWith('owner');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock/unlock', ['code' => '4321'])
            ->assertOk()->assertJson(['locked' => false]);

        $res = $this->withToken($token)->getJson('/api/dashboard/stats')->assertOk();

        $this->assertNull($res->json('locked'));
        $this->assertNotNull($res->json('total_outstanding_cents'));
    }

    public function test_wrong_code_does_not_unlock(): void
    {
        $user = $this->userWith('owner');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock/unlock', ['code' => '0000'])
            ->assertStatus(422);

        $this->assertTrue($this->withToken($token)->getJson('/api/dashboard/stats')->json('locked'));
    }

    public function test_repeated_wrong_codes_are_throttled(): void
    {
        $user = $this->userWith('owner');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);

        // A 4-digit code falls in seconds without a limit on guesses.
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)
                ->postJson('/api/dashboard/lock/unlock', ['code' => '0000'])
                ->assertStatus(422);
        }

        $this->withToken($token)
            ->postJson('/api/dashboard/lock/unlock', ['code' => '0000'])
            ->assertStatus(429);
    }

    public function test_lock_is_per_user_and_does_not_affect_colleagues(): void
    {
        $accountant = $this->userWith('accountant');
        $owner = $this->userWith('owner');
        $superadmin = $this->userWith('superadmin');

        $this->withToken($this->token($accountant))
            ->postJson('/api/dashboard/lock', ['code' => '1111', 'code_confirmation' => '1111']);

        $this->assertTrue(
            $this->withToken($this->token($accountant))->getJson('/api/dashboard/stats')->json('locked')
        );

        foreach ([$owner, $superadmin] as $other) {
            $res = $this->withToken($this->token($other))->getJson('/api/dashboard/stats')->assertOk();
            $this->assertNull($res->json('locked'), 'another user must be unaffected');
            $this->assertNotNull($res->json('total_outstanding_cents'));
        }
    }

    public function test_each_role_sets_an_independent_code(): void
    {
        $accountant = $this->userWith('accountant');
        $owner = $this->userWith('owner');

        $this->withToken($this->token($accountant))
            ->postJson('/api/dashboard/lock', ['code' => '1111', 'code_confirmation' => '1111']);
        $this->withToken($this->token($owner))
            ->postJson('/api/dashboard/lock', ['code' => '2222', 'code_confirmation' => '2222']);

        // The owner's code must not open the accountant's dashboard.
        $this->withToken($this->token($accountant))
            ->postJson('/api/dashboard/lock/unlock', ['code' => '2222'])
            ->assertStatus(422);

        $this->withToken($this->token($accountant))
            ->postJson('/api/dashboard/lock/unlock', ['code' => '1111'])
            ->assertOk();
    }

    public function test_relocking_does_not_let_a_bystander_overwrite_the_code(): void
    {
        $user = $this->userWith('owner');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);
        $this->withToken($token)->postJson('/api/dashboard/lock/unlock', ['code' => '4321']);

        // Re-lock while unlocked, supplying a different code.
        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '9999', 'code_confirmation' => '9999'])
            ->assertOk();

        // The original code still governs; the supplied one was ignored.
        $this->withToken($token)
            ->postJson('/api/dashboard/lock/unlock', ['code' => '9999'])
            ->assertStatus(422);
        $this->withToken($token)
            ->postJson('/api/dashboard/lock/unlock', ['code' => '4321'])
            ->assertOk();
    }

    public function test_export_is_blocked_while_locked_and_allowed_once_unlocked(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234']);

        $this->withToken($token)
            ->get('/api/reports/outstanding-debts/xlsx')
            ->assertStatus(423);

        $this->withToken($token)->postJson('/api/dashboard/lock/unlock', ['code' => '1234']);

        $this->withToken($token)
            ->get('/api/reports/outstanding-debts/xlsx')
            ->assertOk();
    }

    public function test_lock_can_be_removed_with_the_account_password(): void
    {
        $user = $this->userWith('owner');
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);

        $this->withToken($token)
            ->deleteJson('/api/dashboard/lock', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->withToken($token)
            ->deleteJson('/api/dashboard/lock', ['password' => 'secret-password'])
            ->assertOk()->assertJson(['configured' => false]);

        $this->assertDatabaseCount('dashboard_locks', 0);
        $this->assertNull($this->withToken($token)->getJson('/api/dashboard/stats')->json('locked'));
    }

    public function test_code_is_stored_only_as_a_hash(): void
    {
        $user = $this->userWith('owner');

        $this->withToken($this->token($user))
            ->postJson('/api/dashboard/lock', ['code' => '4321', 'code_confirmation' => '4321']);

        $hash = DashboardLock::where('user_id', $user->id)->value('code_hash');
        $this->assertNotSame('4321', $hash);
        $this->assertTrue(Hash::check('4321', $hash));
    }

    /**
     * The lock only redacts a read-only stats response, but that is worth
     * pinning: registering a student is the school's daily work, and it must be
     * completely unaffected by whether the dashboard is showing its numbers.
     */
    public function test_student_registration_still_works_while_locked(): void
    {
        $user = $this->userWith('accountant');
        $token = $this->token($user);

        $year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $this->school->id, 'name' => 'Darasa la 1', 'sort_order' => 1,
        ]);

        $this->withToken($token)
            ->postJson('/api/dashboard/lock', ['code' => '1234', 'code_confirmation' => '1234'])
            ->assertOk();

        $payload = [
            'first_name' => 'Juma', 'last_name' => 'Mfungo',
            'gender' => 'male', 'date_of_birth' => '2015-05-02',
            'status' => 'active',
            'school_id' => $this->school->id,
            'school_class_id' => $class->id,
            'academic_year_id' => $year->id,
            'term_id' => $term->id,
            'enrollment_date' => '2026-01-10',
            'total_tuition_fee_cents' => 50000000,
            'guardians' => [[
                'full_name' => 'Asha Mfungo', 'relationship' => 'mother', 'phone' => '0712345678',
            ]],
        ];

        $response = $this->withToken($token)->postJson('/api/students/register', $payload);

        // Full creation cannot be asserted here: the migration that widened the
        // gender enum from me/ke to male/female is a MySQL-only ALTER, so the
        // SQLite test database still rejects 'male'. What this test can prove —
        // and the only thing the lock could plausibly break — is that the write
        // path is neither gated (423) nor rejected by the lock, and that the
        // request passes validation and reaches the service unchanged.
        $this->assertNotSame(423, $response->status(), 'registration must not be gated by the lock');
        $this->assertNotSame(422, $response->status(), 'the lock must not affect validation');

        // And the dashboard is still locked afterwards — registering did not
        // quietly clear anyone's lock.
        $this->assertTrue($this->withToken($token)->getJson('/api/dashboard/stats')->json('locked'));
    }

    public function test_short_codes_are_rejected(): void
    {
        $user = $this->userWith('owner');

        $this->withToken($this->token($user))
            ->postJson('/api/dashboard/lock', ['code' => '12', 'code_confirmation' => '12'])
            ->assertStatus(422);

        $this->assertDatabaseCount('dashboard_locks', 0);
    }
}
