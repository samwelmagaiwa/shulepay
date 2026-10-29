<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = [], string $role = 'accountant'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $school = School::firstOrCreate(['code' => 'SEC'], ['name' => 'Sec', 'slug' => 'sec', 'level' => 'primary', 'is_active' => true]);
        $u = User::factory()->create(['school_id' => $school->id, 'password' => 'Secret123'] + $attrs);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $u;
    }

    public function test_an_account_on_its_initial_password_can_only_change_it(): void
    {
        $u = $this->user(['must_change_password' => true]);
        $h = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];

        $this->getJson('/api/settings/profile', $h)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/students', $h)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/auth/me', $h)->assertOk();

        $this->postJson('/api/settings/change-password', [
            'current_password' => 'Secret123', 'password' => 'Brandnew456', 'password_confirmation' => 'Brandnew456',
        ], $h)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/settings/profile', $h)->assertOk();
    }

    public function test_a_weak_new_password_is_refused(): void
    {
        $u = $this->user();
        $h = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];

        foreach (['onlyletters', '12345678'] as $weak) {
            $this->postJson('/api/settings/change-password', [
                'current_password' => 'Secret123', 'password' => $weak, 'password_confirmation' => $weak,
            ], $h)->assertUnprocessable();
        }
    }

    public function test_changing_the_password_signs_other_devices_out(): void
    {
        $u = $this->user();
        $other = $u->createToken('phone');
        $h = ['Authorization' => 'Bearer '.$u->createToken('laptop')->plainTextToken];

        $this->postJson('/api/settings/change-password', [
            'current_password' => 'Secret123', 'password' => 'Brandnew456', 'password_confirmation' => 'Brandnew456',
        ], $h)->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertSame(1, $u->tokens()->count());
    }

    public function test_login_attempts_are_limited_per_account_without_locking_others_out(): void
    {
        RateLimiter::clear('login|victim@example.com|127.0.0.1');
        $victim = $this->user(['email' => 'victim@example.com']);
        $other = $this->user(['email' => 'other@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong-'.$i])->assertUnprocessable();
        }
        $this->postJson('/api/auth/login', ['email' => 'victim@example.com', 'password' => 'wrong'])->assertStatus(429);

        // a different account from the same address still gets through
        $this->postJson('/api/auth/login', ['email' => 'other@example.com', 'password' => 'Secret123'])->assertOk();
    }

    public function test_the_client_address_comes_from_the_forwarded_header_of_a_trusted_proxy_only(): void
    {
        $seen = null;
        Route::get('/api/_ip', function (Request $r) use (&$seen) {
            $seen = $r->ip();

            return 'ok';
        });

        // proxy in a private range, real client behind it
        $this->withServerVariables(['REMOTE_ADDR' => '172.19.0.3'])->get('/api/_ip', ['X-Forwarded-For' => '41.59.1.1']);
        $this->assertSame('41.59.1.1', $seen);

        // a spoofed leftmost value is ignored: the first untrusted hop from the right wins
        $this->withServerVariables(['REMOTE_ADDR' => '172.19.0.3'])->get('/api/_ip', ['X-Forwarded-For' => '6.6.6.6, 41.59.1.1, 172.19.0.2']);
        $this->assertSame('41.59.1.1', $seen);

        // a direct caller from the internet cannot choose its address
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/api/_ip', ['X-Forwarded-For' => '1.2.3.4']);
        $this->assertSame('203.0.113.9', $seen);
    }

    public function test_svg_logos_are_refused(): void
    {
        $u = $this->user([], 'owner');
        $h = ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');

        $this->post('/api/branding', ['logo' => $svg], $h + ['Accept' => 'application/json'])->assertStatus(422);
    }
}
