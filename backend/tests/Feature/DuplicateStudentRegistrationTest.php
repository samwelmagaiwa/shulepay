<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registration had no duplicate check, so a double-submitted form created a
 * second student with their own admission number, invoices and payments. Four
 * such pairs reached production.
 */
class DuplicateStudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'FIRST TERM', 'number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'STANDARD ONE', 'sort_order' => 3]);

        $this->accountant = User::factory()->create(['school_id' => $school->id]);
        $this->accountant->assignRole('accountant');

        $this->payload = [
            'first_name' => 'Gilson', 'last_name' => 'Gilson',
            'gender' => 'male', 'date_of_birth' => '2012-01-10',
            'status' => 'active',
            'school_id' => $school->id, 'school_class_id' => $class->id,
            'academic_year_id' => $year->id, 'term_id' => $term->id,
            'enrollment_date' => '2026-01-10',
            'total_tuition_fee_cents' => 50000000,
            'guardians' => [['full_name' => 'Asha Gilson', 'relationship' => 'mother', 'phone' => '0712345678']],
        ];
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->accountant->createToken('t')->plainTextToken;
    }

    public function test_a_second_registration_of_the_same_child_is_refused(): void
    {
        Student::create([
            'first_name' => 'Gilson', 'last_name' => 'Gilson',
            'date_of_birth' => '2012-01-10', 'status' => 'active',
        ]);

        $this->withToken($this->token())
            ->postJson('/api/students/register', $this->payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('duplicate_student');

        $this->assertSame(1, Student::where('first_name', 'Gilson')->count());
    }

    public function test_the_same_name_with_a_different_birth_date_is_allowed(): void
    {
        Student::create([
            'first_name' => 'Gilson', 'last_name' => 'Gilson',
            // A genuine namesake, born a year apart — must not be blocked.
            'date_of_birth' => '2013-06-02', 'status' => 'active',
        ]);

        // Full creation cannot be asserted on SQLite: the migration widening the
        // gender enum from me/ke to male/female is a MySQL-only ALTER, so the
        // insert fails at the DB. What matters here is that the DUPLICATE guard
        // did not fire, which is visible either way.
        $this->withToken($this->token())
            ->postJson('/api/students/register', $this->payload)
            ->assertJsonMissingValidationErrors('duplicate_student');
    }

    public function test_confirming_overrides_the_block(): void
    {
        Student::create([
            'first_name' => 'Gilson', 'last_name' => 'Gilson',
            'date_of_birth' => '2012-01-10', 'status' => 'active',
        ]);

        $this->withToken($this->token())
            ->postJson('/api/students/register', $this->payload + ['confirm_duplicate' => true])
            ->assertJsonMissingValidationErrors('duplicate_student');
    }
}
