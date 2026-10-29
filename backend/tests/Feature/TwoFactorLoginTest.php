<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\School;
use App\Models\User;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{0: string, 1: string}> */
    private array $sms = [];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
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
        $school = School::create(['name' => 'Tf', 'code' => 'TFA', 'slug' => 'tfa', 'level' => 'primary', 'is_active' => true]);
        $this->user = User::factory()->create([
            'school_id' => $school->id, 'email' => 'tf@example.com', 'phone' => '0712000111', 'password' => 'Secret123', '2fa_enabled' => true,
        ]);
        $this->user->assignRole('accountant');
    }

    private function challenge(): string
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->postJson('/api/auth/login', ['email' => 'tf@example.com', 'password' => 'Secret123'])
            ->assertOk()->assertJsonPath('requires_2fa', true)->assertJsonMissingPath('user_id')->json('challenge');
    }

    private function code(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sms)[1], $m);

        return $m[1];
    }

    public function test_a_user_id_alone_can_no_longer_trigger_sms_or_a_login(): void
    {
        $this->postJson('/api/2fa/request', ['user_id' => $this->user->id])->assertUnprocessable();
        $this->postJson('/api/2fa/verify', ['user_id' => $this->user->id, 'code' => '123456'])->assertUnprocessable();
        $this->postJson('/api/2fa/request', ['challenge' => str_repeat('a', 64)])->assertUnprocessable()->assertJsonPath('code', 'TWO_FA_EXPIRED');
        $this->assertSame([], $this->sms);
    }

    public function test_the_full_two_step_sign_in(): void
    {
        $challenge = $this->challenge();

        $this->postJson('/api/2fa/request', ['challenge' => $challenge])->assertOk();
        $this->assertSame('+255712000111', $this->sms[0][0]);
        $this->assertNotSame($this->code(), OtpCode::first()->code, 'the code is stored hashed');

        $this->postJson('/api/2fa/verify', ['challenge' => $challenge, 'code' => $this->code()])
            ->assertOk()->assertJsonStructure(['token', 'user']);

        // the challenge is spent
        $this->postJson('/api/2fa/verify', ['challenge' => $challenge, 'code' => $this->code()])->assertUnprocessable();
    }

    public function test_codes_cannot_be_requested_again_within_a_minute(): void
    {
        $challenge = $this->challenge();
        $this->postJson('/api/2fa/request', ['challenge' => $challenge])->assertOk();
        $this->postJson('/api/2fa/request', ['challenge' => $challenge])->assertStatus(429);
        $this->assertCount(1, $this->sms);
    }

    public function test_five_wrong_codes_cancel_the_challenge(): void
    {
        $challenge = $this->challenge();
        $this->postJson('/api/2fa/request', ['challenge' => $challenge])->assertOk();
        $right = $this->code();
        $wrong = $right === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/2fa/verify', ['challenge' => $challenge, 'code' => $wrong])->assertUnprocessable()->assertJsonMissing(['code' => 'TWO_FA_EXPIRED']);
        }
        $this->postJson('/api/2fa/verify', ['challenge' => $challenge, 'code' => $right])->assertUnprocessable()->assertJsonPath('code', 'TWO_FA_EXPIRED');
    }
}
