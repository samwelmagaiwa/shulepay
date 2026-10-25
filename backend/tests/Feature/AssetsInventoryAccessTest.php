<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Assets, stock and stationery requests are kept per school: a list shows the school's own rows, and
 * naming another school in the request — or switching to "all schools" — does not open it.
 */
class AssetsInventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private School $mine;

    private School $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['accountant', 'teacher_pri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->mine = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->theirs = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
        $this->asset($this->mine, 'Alpha projector');
        $this->asset($this->theirs, 'Beta generator');
    }

    private function asset(School $school, string $name): Asset
    {
        return Asset::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'asset_tag' => 'AST-'.$school->id.'-1', 'name' => $name, 'category' => 'equipment', 'cost_cents' => 100000,
            'current_value_cents' => 90000, 'purchase_date' => '2026-01-10', 'condition' => 'good',
        ]);
    }

    private function as(string $role): static
    {
        $u = User::factory()->create(['school_id' => $this->mine->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    public function test_the_asset_list_is_the_schools_own(): void
    {
        $names = collect($this->as('accountant')->getJson('/api/assets')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Alpha projector'], $names);
    }

    public function test_naming_another_school_is_refused(): void
    {
        $this->as('accountant')->getJson("/api/assets?school_id={$this->theirs->id}")->assertForbidden();
        $this->as('accountant')->getJson("/api/inventory/items?school_id={$this->theirs->id}")->assertForbidden();
        $this->as('accountant')->getJson("/api/stationary?school_id={$this->theirs->id}")->assertStatus(403);
    }

    public function test_all_schools_mode_shows_only_the_users_schools(): void
    {
        $names = collect($this->as('accountant')->withHeaders(['X-School-Id' => '0'])->getJson('/api/assets')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertNotContains('Beta generator', $names);
    }

    public function test_teachers_do_not_reach_the_asset_register(): void
    {
        $this->as('teacher_pri')->getJson('/api/assets')->assertForbidden();
    }
}
