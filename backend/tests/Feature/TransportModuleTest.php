<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolNotification;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Transport: vehicle documents with expiry alerts and fuel logs, kept per school.
 */
class TransportModuleTest extends TestCase
{
    use RefreshDatabase;

    private School $mine;

    private School $theirs;

    private Vehicle $bus;

    private Vehicle $foreignBus;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['accountant', 'teacher_pri'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->mine = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->theirs = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
        $this->bus = $this->vehicle($this->mine, 'T 100 AAA');
        $this->foreignBus = $this->vehicle($this->theirs, 'T 200 BBB');
    }

    private function vehicle(School $school, string $plate): Vehicle
    {
        return Vehicle::withoutGlobalScopes()->create([
            'school_id' => $school->id, 'plate_number' => $plate, 'make' => 'Toyota', 'model' => 'Coaster',
            'year' => 2020, 'capacity' => 30, 'type' => 'bus', 'status' => 'active',
        ]);
    }

    private function as(string $role): static
    {
        $u = User::factory()->create(['school_id' => $this->mine->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    public function test_documents_track_expiry_and_raise_alerts(): void
    {
        $id = $this->bus->id;
        $this->as('accountant')->postJson("/api/transport/vehicles/{$id}/documents", [
            'type' => 'insurance', 'provider' => 'Jubilee', 'issue_date' => now()->subYear()->toDateString(), 'expiry_date' => now()->addDays(10)->toDateString(),
        ])->assertSuccessful();
        $this->as('accountant')->postJson("/api/transport/vehicles/{$id}/documents", [
            'type' => 'road_licence', 'expiry_date' => now()->addYear()->toDateString(),
        ])->assertSuccessful();

        $docs = $this->as('accountant')->getJson("/api/transport/vehicles/{$id}/documents")->assertOk()->json('data');
        $this->assertCount(2, $docs);
        $this->assertSame(['expiring', 'valid'], collect($docs)->sortBy('expiry_date')->pluck('status')->values()->all());

        $alerts = $this->as('accountant')->getJson('/api/transport/documents/alerts')->assertOk()->json('data');
        $this->assertNotEmpty($alerts, 'a document expiring in 10 days is flagged');
    }

    public function test_an_expiry_before_the_issue_date_is_refused(): void
    {
        $this->as('accountant')->postJson("/api/transport/vehicles/{$this->bus->id}/documents", [
            'type' => 'insurance', 'issue_date' => '2026-06-01', 'expiry_date' => '2026-01-01',
        ])->assertStatus(422);
    }

    public function test_fuel_logs_are_recorded_and_summarised(): void
    {
        $id = $this->bus->id;
        $this->as('accountant')->postJson("/api/transport/vehicles/{$id}/fuel", [
            'fuelled_on' => now()->subDays(5)->toDateString(), 'litres' => 40, 'cost_cents' => 12000000, 'odometer_km' => 10000, 'full_tank' => true,
        ])->assertSuccessful();
        $this->as('accountant')->postJson("/api/transport/vehicles/{$id}/fuel", [
            'fuelled_on' => now()->subDay()->toDateString(), 'litres' => 40, 'cost_cents' => 12000000, 'odometer_km' => 10400, 'full_tank' => true,
        ])->assertSuccessful();

        $this->as('accountant')->getJson("/api/transport/vehicles/{$id}/fuel")->assertOk();
        $this->as('accountant')->getJson('/api/transport/fuel/summary')->assertOk();
        $this->as('accountant')->postJson("/api/transport/vehicles/{$id}/fuel", [
            'fuelled_on' => now()->addDay()->toDateString(), 'litres' => 10, 'cost_cents' => 1, 'odometer_km' => 10500,
        ])->assertStatus(422);   // a fill-up cannot be in the future
    }

    public function test_only_finance_staff_use_transport(): void
    {
        $this->as('teacher_pri')->getJson("/api/transport/vehicles/{$this->bus->id}/documents")->assertForbidden();
        $this->as('teacher_pri')->getJson('/api/transport/documents/alerts')->assertForbidden();
    }

    public function test_another_schools_vehicle_cannot_be_read_or_written(): void
    {
        $foreign = $this->foreignBus->id;
        $this->as('accountant')->getJson("/api/transport/vehicles/{$foreign}/documents")->assertStatus(404);
        $this->as('accountant')->postJson("/api/transport/vehicles/{$foreign}/documents", ['type' => 'insurance', 'expiry_date' => '2030-01-01'])->assertStatus(404);
        $this->as('accountant')->postJson("/api/transport/vehicles/{$foreign}/fuel", ['fuelled_on' => '2026-01-01', 'litres' => 5, 'cost_cents' => 1, 'odometer_km' => 1])->assertStatus(404);

        // nor can a vehicle be created inside another school by naming it in the request
        $this->as('accountant')->postJson('/api/transport/vehicles', [
            'plate_number' => 'T 300 CCC', 'school_id' => $this->theirs->id, 'make' => 'X', 'model' => 'Y', 'year' => 2022, 'capacity' => 10, 'type' => 'van',
        ])->assertForbidden();
        $this->assertDatabaseMissing('vehicles', ['plate_number' => 'T 300 CCC']);
    }

    public function test_the_daily_alert_command_tells_the_owner_and_accountant_once(): void
    {
        $this->as('accountant')->postJson("/api/transport/vehicles/{$this->bus->id}/documents", [
            'type' => 'insurance', 'expiry_date' => now()->addDays(7)->toDateString(),
        ])->assertSuccessful();
        // a document of the other school must not alert this one
        VehicleDocument::withoutGlobalScopes()->create([
            'vehicle_id' => $this->foreignBus->id, 'type' => 'insurance', 'expiry_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->artisan('transport:document-alerts')->assertSuccessful();

        $mine = SchoolNotification::where('school_id', $this->mine->id)->get();
        $this->assertEqualsCanonicalizing(['owner', 'accountant'], $mine->pluck('recipient_role')->all());
        $this->assertStringContainsString('T 100 AAA', $mine->first()->body.$mine->first()->title);
        $this->assertStringNotContainsString('T 200 BBB', $mine->pluck('body')->join(' '));

        $this->artisan('transport:document-alerts')->assertSuccessful();
        $this->assertSame(2, SchoolNotification::where('school_id', $this->mine->id)->count(), 'the same alert is not sent twice');
    }
}
