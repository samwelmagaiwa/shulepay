<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Classes, academic years and terms: the people who keep them (owner, academic coordinators, and for the
 * calendar the accountant) may change them — but only in their own school, and a class may carry a stream.
 */
class SchoolSetupAccessTest extends TestCase
{
    use RefreshDatabase;

    private School $mine;

    private School $theirs;

    private SchoolClass $foreignClass;

    private AcademicYear $foreignYear;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['owner', 'academic_pri', 'accountant', 'teacher_pri', 'superadmin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->mine = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'secondary', 'is_active' => true]);
        $this->theirs = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'secondary', 'is_active' => true]);
        $this->foreignClass = SchoolClass::create(['school_id' => $this->theirs->id, 'name' => 'Form 1', 'sort_order' => 1]);
        $this->foreignYear = AcademicYear::create(['school_id' => $this->theirs->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
    }

    private function as(string $role): static
    {
        $u = User::factory()->create(['school_id' => $this->mine->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    public function test_an_academic_coordinator_keeps_classes_with_streams_in_their_own_school(): void
    {
        $r = $this->as('academic_pri')->postJson('/api/school-classes', ['name' => 'Form 1', 'stream' => 'A', 'school_id' => $this->mine->id])->assertCreated();
        $this->assertSame('A', $r->json('stream'));

        $this->as('academic_pri')->postJson('/api/school-classes', ['name' => 'Form 1', 'stream' => 'B', 'school_id' => $this->mine->id])->assertCreated();
        // the same name and stream twice is refused, with or without a stream
        $this->as('academic_pri')->postJson('/api/school-classes', ['name' => 'Form 1', 'stream' => 'A', 'school_id' => $this->mine->id])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as('academic_pri')->postJson('/api/school-classes', ['name' => 'Form 2', 'school_id' => $this->mine->id])->assertCreated();
        $this->as('academic_pri')->postJson('/api/school-classes', ['name' => 'Form 2', 'school_id' => $this->mine->id])->assertStatus(422);
    }

    public function test_teachers_and_accountants_do_not_keep_classes(): void
    {
        $this->as('teacher_pri')->postJson('/api/school-classes', ['name' => 'X', 'school_id' => $this->mine->id])->assertForbidden();
        $this->as('accountant')->postJson('/api/school-classes', ['name' => 'X', 'school_id' => $this->mine->id])->assertForbidden();
    }

    public function test_nobody_changes_or_reads_another_schools_classes_by_id(): void
    {
        $id = $this->foreignClass->id;
        $this->as('owner')->getJson("/api/school-classes/{$id}")->assertForbidden();
        $this->as('owner')->putJson("/api/school-classes/{$id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->as('owner')->deleteJson("/api/school-classes/{$id}")->assertForbidden();
        $this->as('owner')->postJson('/api/school-classes', ['name' => 'Planted', 'school_id' => $this->theirs->id])->assertForbidden();
        $this->as('owner')->getJson("/api/school-classes?school_id={$this->theirs->id}")->assertForbidden();
        $this->assertSame('Form 1', $this->foreignClass->fresh()->name);
    }

    public function test_the_calendar_is_kept_by_finance_staff_and_coordinators_in_their_own_school_only(): void
    {
        $body = ['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'school_id' => $this->mine->id];
        $this->as('academic_pri')->postJson('/api/academic-years', $body)->assertCreated();
        $this->as('accountant')->postJson('/api/academic-years', ['name' => '2028'] + $body)->assertCreated();
        $this->as('teacher_pri')->postJson('/api/academic-years', ['name' => '2029'] + $body)->assertForbidden();

        $id = $this->foreignYear->id;
        $this->as('accountant')->getJson("/api/academic-years/{$id}")->assertForbidden();
        $this->as('accountant')->putJson("/api/academic-years/{$id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->as('academic_pri')->deleteJson("/api/academic-years/{$id}")->assertForbidden();
        $this->as('accountant')->postJson('/api/academic-years', ['name' => '2030', 'school_id' => $this->theirs->id] + $body)->assertForbidden();
        $this->assertDatabaseHas('academic_years', ['id' => $id, 'name' => '2026']);
    }

    public function test_a_superadmin_can_work_in_any_school(): void
    {
        $this->as('superadmin')->putJson("/api/school-classes/{$this->foreignClass->id}", ['name' => 'Form One'])->assertOk();
        $this->assertSame('Form One', $this->foreignClass->fresh()->name);
    }
}
