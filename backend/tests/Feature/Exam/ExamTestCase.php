<?php

namespace Tests\Feature\Exam;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Shared set-up of the finance and school-module feature tests: one school, the roles, and a way to act as a
 * role. The bundle these tests came from calls the owner role 'admin'; here it is 'owner'.
 */
abstract class ExamTestCase extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['owner', 'accountant', 'head_teacher', 'academic_pri', 'teacher_pri', 'teacher_sec', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
    }

    /** Sign in as a new user with this role in the school; returns the user. */
    protected function actingAsRole(string $role): User
    {
        $role = $role === 'admin' ? 'owner' : $role;
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole($role);
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('t')->plainTextToken);

        return $user;
    }
}
