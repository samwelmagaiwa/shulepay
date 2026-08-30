<?php

namespace Tests\Feature;

use App\Http\Controllers\SessionSettingController;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The idle-timeout policy every authenticated user's session timer reads.
 * What matters:
 *  - anyone logged in can read it (their own idle timer depends on it)
 *  - only owner/superadmin can change it
 *  - out-of-range input is rejected outright (422), not silently clamped
 *  - a corrupted/out-of-range stored value is clamped back to the default
 *    on read, rather than handed to the client as-is
 */
class SessionSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);
        $user->assignRole($role);

        return $user;
    }

    public function test_defaults_are_returned_when_nothing_is_configured(): void
    {
        $response = $this->actingAs($this->userWith('accountant'))->getJson('/api/session-settings');

        $response->assertOk()->assertJson([
            'idle_minutes' => SessionSettingController::DEFAULTS['idle_minutes'],
            'warning_seconds' => SessionSettingController::DEFAULTS['warning_seconds'],
        ]);
    }

    public function test_owner_can_update_the_policy(): void
    {
        $response = $this->actingAs($this->userWith('owner'))
            ->putJson('/api/session-settings', ['idle_minutes' => 15, 'warning_seconds' => 45]);

        $response->assertOk()->assertJson(['idle_minutes' => 15, 'warning_seconds' => 45]);

        $this->assertEquals(
            ['idle_minutes' => 15, 'warning_seconds' => 45],
            SystemSetting::get('session_timeout')
        );
    }

    public function test_accountant_cannot_update_the_policy(): void
    {
        $this->actingAs($this->userWith('accountant'))
            ->putJson('/api/session-settings', ['idle_minutes' => 15, 'warning_seconds' => 45])
            ->assertForbidden();
    }

    public function test_out_of_range_values_are_rejected(): void
    {
        $this->actingAs($this->userWith('owner'))
            ->putJson('/api/session-settings', ['idle_minutes' => 0, 'warning_seconds' => 45])
            ->assertUnprocessable();

        $this->actingAs($this->userWith('owner'))
            ->putJson('/api/session-settings', ['idle_minutes' => 15, 'warning_seconds' => 5000])
            ->assertUnprocessable();
    }

    public function test_a_corrupted_stored_value_is_clamped_back_to_default_on_read(): void
    {
        SystemSetting::set('session_timeout', ['idle_minutes' => 9999, 'warning_seconds' => -5]);

        $response = $this->actingAs($this->userWith('accountant'))->getJson('/api/session-settings');

        $response->assertOk()->assertJson([
            'idle_minutes' => SessionSettingController::DEFAULTS['idle_minutes'],
            'warning_seconds' => SessionSettingController::DEFAULTS['warning_seconds'],
        ]);
    }
}
