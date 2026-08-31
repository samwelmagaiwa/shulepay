<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Superadmin must reach everything.
 *
 * The bypass used to be written by hand in individual controllers, so each new
 * check had to remember it — and teachingStaff() did not, which locked a
 * superadmin out of the academic and attendance routes entirely. These tests
 * pin the two central mechanisms that replaced that: Gate::before, and
 * UserRole::guard() appending SuperAdmin to every route guard it builds.
 */
class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        foreach (UserRole::all() as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $school = School::firstOrCreate(
            ['code' => 'MSG'],
            ['name' => 'Msingi', 'slug' => 'msingi', 'level' => 'primary']
        );

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function test_every_route_guard_admits_a_superadmin(): void
    {
        // teachingStaff() deliberately still names only teachers; it is guard()
        // that must widen it, so the lists stay honest for other callers.
        $this->assertNotContains(UserRole::SuperAdmin->value, UserRole::teachingStaff());

        foreach ([
            UserRole::teachingStaff(),
            UserRole::financeStaff(),
            UserRole::adminStaff(),
            UserRole::attendanceMarkers(),
        ] as $roles) {
            $this->assertStringContainsString(
                UserRole::SuperAdmin->value,
                UserRole::guard($roles),
                'a route guard that excludes superadmin locks it out of that module'
            );
        }
    }

    public function test_guard_does_not_duplicate_superadmin(): void
    {
        $guard = UserRole::guard(UserRole::financeStaff());

        $this->assertSame(
            1,
            substr_count($guard, UserRole::SuperAdmin->value),
            'a repeated role makes the middleware string wrong, not merely untidy'
        );
    }

    public function test_a_superadmin_passes_any_gate(): void
    {
        $user = $this->superadmin();

        // An ability nothing defines: only Gate::before can allow it.
        $this->assertTrue(Gate::forUser($user)->allows('anything-at-all'));
        $this->assertTrue(Gate::forUser($user)->allows('invoices.edit_restricted'));
    }

    public function test_a_non_superadmin_is_not_granted_by_the_hook(): void
    {
        foreach (UserRole::all() as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $user = User::factory()->create();
        $user->assignRole(UserRole::Accountant->value);

        // Gate::before must return null, not false, for everyone else — false
        // would deny outright and stop real gates from ever running.
        $this->assertFalse(Gate::forUser($user)->allows('anything-at-all'));
    }

    public function test_a_superadmin_holds_every_permission_in_the_matrix(): void
    {
        $user = $this->superadmin();

        Permission::firstOrCreate(['name' => 'students.delete', 'guard_name' => 'web']);

        // Not granted directly, and not via the role — only the gate hook.
        $this->assertFalse($user->permissions->contains('name', 'students.delete'));
        $this->assertTrue(Gate::forUser($user)->allows('students.delete'));
    }
}
