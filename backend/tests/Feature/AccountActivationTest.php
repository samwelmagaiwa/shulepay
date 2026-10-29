<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A new account is activated with a code sent by SMS, then the user chooses a password. */
class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{0: string, 1: string}> */
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['owner', 'teacher_pri', 'parent'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->sms = [];
        $outbox = &$this->sms;
        $this->app->bind(SmsGatewayInterface::class, function () use (&$outbox) {
            return new class($outbox) implements SmsGatewayInterface
            {
                public function __construct(private array &$outbox) {}

                public function send(string $to, string $message): bool
                {
                    $this->outbox[] = [$to, $message];

                    return true;
                }
            };
        });
    }

    /** A request that came with a bearer token leaves the sanctum guard as the default; a real sign-in starts clean. */
    private function signIn(array $body): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->postJson('/api/auth/login', $body);
    }

    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sms)[1], $m);

        return $m[1];
    }

    private function createStaff(array $over = []): array
    {
        $school = School::firstOrCreate(['code' => 'ACT'], ['name' => 'Act', 'slug' => 'act', 'level' => 'primary', 'is_active' => true]);
        $owner = User::factory()->create(['school_id' => $school->id]);
        $owner->assignRole('owner');
        $this->app['auth']->forgetGuards();
        $res = $this->withToken($owner->createToken('t')->plainTextToken)->postJson('/api/users', array_merge([
            'name' => 'New Teacher', 'email' => 'new.teacher@example.com', 'phone' => '0712345678', 'role' => 'teacher_pri',
        ], $over));

        return [$res, $school];
    }

    public function test_creating_a_user_texts_a_code_and_hands_out_no_password(): void
    {
        [$res] = $this->createStaff();

        $res->assertCreated()->assertJsonPath('activation_sms_sent', true);
        $this->assertCount(1, $this->sms);
        $this->assertSame('+255712345678', $this->sms[0][0]);
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $this->sms[0][1]);

        $user = User::where('email', 'new.teacher@example.com')->first();
        $this->assertTrue($user->must_change_password);
        $this->assertFalse(Hash::check('SCHOOL', $user->password));
        $this->assertNotSame($this->lastCode(), $user->activation_code_hash, 'only a hash of the code is stored');

        $this->signIn(['email' => 'new.teacher@example.com', 'password' => 'SCHOOL'])->assertUnprocessable();
    }

    public function test_a_phone_number_is_required_to_create_a_user(): void
    {
        [$res] = $this->createStaff(['phone' => '']);
        $res->assertUnprocessable();
    }

    public function test_code_then_password_activates_the_account_and_it_can_sign_in(): void
    {
        $this->createStaff();
        $code = $this->lastCode();

        $verify = $this->postJson('/api/auth/activate/verify', ['identifier' => '0712 345 678', 'code' => $code])->assertOk();
        $token = $verify->json('token');
        $this->assertSame(64, strlen($token));

        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Brandnew456', 'password_confirmation' => 'Brandnew456'])->assertOk();

        $this->signIn(['email' => 'new.teacher@example.com', 'password' => 'Brandnew456'])->assertOk();
        $this->assertFalse(User::where('email', 'new.teacher@example.com')->first()->must_change_password);
    }

    public function test_the_email_address_works_as_identifier_too(): void
    {
        $this->createStaff();

        $this->postJson('/api/auth/activate/verify', ['identifier' => 'New.Teacher@example.com', 'code' => $this->lastCode()])->assertOk();
    }

    public function test_the_two_passwords_must_match_and_be_strong(): void
    {
        $this->createStaff();
        $token = $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $this->lastCode()])->json('token');

        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Brandnew456', 'password_confirmation' => 'Different789'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Short1', 'password_confirmation' => 'Short1'])
            ->assertUnprocessable();

        // the token survives mistakes and still works once the form is right
        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Brandnew456', 'password_confirmation' => 'Brandnew456'])->assertOk();
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_guesses_burn_the_code(): void
    {
        $this->createStaff();
        $right = $this->lastCode();
        $wrong = $right === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $wrong])->assertUnprocessable();
        }
        // the sixth try within the minute is stopped by the rate limit, right code or not
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $right])->assertStatus(429);

        // later the code is already burned by the five wrong guesses
        $this->travel(2)->minutes();
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $right])->assertUnprocessable();

        // a fresh code works
        $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertOk();
        $this->assertCount(2, $this->sms);
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $this->lastCode()])->assertOk();
    }

    public function test_a_code_expires_and_works_only_once(): void
    {
        $this->createStaff();
        $code = $this->lastCode();

        $this->travel(49)->hours();
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $code])->assertUnprocessable();
        $this->travelBack();

        $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertOk();
        $code = $this->lastCode();
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $code])->assertOk();
        $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $code])->assertUnprocessable();
    }

    public function test_resend_never_reveals_whether_an_account_exists_and_is_rate_limited(): void
    {
        $this->createStaff();

        $known = $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/auth/activate/resend', ['identifier' => '0799999999'])->assertOk()->json('message');
        $this->assertSame($known, $unknown);
        $this->assertCount(1, $this->sms, 'a second code is not sent within a minute, and nothing is sent to an unknown number');

        $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertOk();
        $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertOk();
        $this->postJson('/api/auth/activate/resend', ['identifier' => '0712345678'])->assertStatus(429);
    }

    public function test_an_activated_or_unknown_identifier_gets_no_token(): void
    {
        $this->postJson('/api/auth/activate/verify', ['identifier' => 'nobody@example.com', 'code' => '123456'])->assertUnprocessable();

        $this->createStaff();
        $token = $this->postJson('/api/auth/activate/verify', ['identifier' => '0712345678', 'code' => $this->lastCode()])->json('token');
        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Brandnew456', 'password_confirmation' => 'Brandnew456'])->assertOk();

        // the token cannot be used a second time to take the password over
        $this->postJson('/api/auth/activate/complete', ['token' => $token, 'password' => 'Hijacked789', 'password_confirmation' => 'Hijacked789'])->assertUnprocessable();
        $this->signIn(['email' => 'new.teacher@example.com', 'password' => 'Brandnew456'])->assertOk();
    }
}
