<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolScopingTest extends TestCase
{
    use RefreshDatabase;

    private School $msingi;

    private School $sekondari;

    private User $accountantA;   // scoped to msingi

    private User $accountantB;   // scoped to sekondari

    private User $owner;         // cross-school

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->msingi = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $this->sekondari = School::create(['name' => 'Sekondari', 'code' => 'SEK', 'slug' => 'sekondari', 'level' => 'secondary']);

        $year1 = AcademicYear::create(['school_id' => $this->msingi->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);
        $year2 = AcademicYear::create(['school_id' => $this->sekondari->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);

        $term1 = Term::create(['academic_year_id' => $year1->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true]);
        $term2 = Term::create(['academic_year_id' => $year2->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true]);

        $classA = SchoolClass::create(['school_id' => $this->msingi->id, 'name' => 'Darasa la 1', 'sort_order' => 1]);
        $classB = SchoolClass::create(['school_id' => $this->sekondari->id, 'name' => 'Kidato cha 1', 'sort_order' => 1]);

        $this->accountantA = User::factory()->create(['school_id' => $this->msingi->id]);
        $this->accountantA->assignRole('accountant');

        $this->accountantB = User::factory()->create(['school_id' => $this->sekondari->id]);
        $this->accountantB->assignRole('accountant');

        $this->owner = User::factory()->create(['school_id' => null]);
        $this->owner->assignRole('superadmin');

        // Create one student in each school
        $studentA = Student::create(['first_name' => 'Juma', 'last_name' => 'Msingi', 'status' => 'active']);
        Enrollment::create(['student_id' => $studentA->id, 'school_id' => $this->msingi->id, 'school_class_id' => $classA->id, 'academic_year_id' => $year1->id, 'admission_number' => 'MSG-1', 'status' => 'active', 'admitted_at' => '2024-01-15']);

        $studentB = Student::create(['first_name' => 'Ali', 'last_name' => 'Sekondari', 'status' => 'active']);
        Enrollment::create(['student_id' => $studentB->id, 'school_id' => $this->sekondari->id, 'school_class_id' => $classB->id, 'academic_year_id' => $year2->id, 'admission_number' => 'SEK-1', 'status' => 'active', 'admitted_at' => '2024-01-15']);

        // Invoices for each student
        Invoice::withoutGlobalScope('school')->create(['student_id' => $studentA->id, 'school_id' => $this->msingi->id, 'term_id' => $term1->id, 'academic_year_id' => $year1->id, 'invoice_number' => 'INV-MSG-001', 'total_amount_cents' => 50000, 'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(), 'generated_by' => $this->accountantA->id]);
        Invoice::withoutGlobalScope('school')->create(['student_id' => $studentB->id, 'school_id' => $this->sekondari->id, 'term_id' => $term2->id, 'academic_year_id' => $year2->id, 'invoice_number' => 'INV-SEK-001', 'total_amount_cents' => 90000, 'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(), 'generated_by' => $this->accountantB->id]);
    }

    /** (a) Active-school scoping hides other school's invoices */
    public function test_school_scope_hides_other_school_invoices(): void
    {
        $token = $this->accountantA->createToken('t')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/invoices');

        $response->assertOk();
        $numbers = collect($response->json('data'))->pluck('invoice_number')->all();
        $this->assertContains('INV-MSG-001', $numbers);
        $this->assertNotContains('INV-SEK-001', $numbers);
    }

    /** (b) "Shule Zote" mode: explicit ?school_id= empty sees both schools */
    public function test_shule_zote_mode_aggregates_both_schools(): void
    {
        $token = $this->owner->createToken('t')->plainTextToken;

        // Pass school_id=0 / omit to get cross-school view
        $response = $this->withToken($token)
            ->getJson('/api/invoices?school_id=0');

        $response->assertOk();
        $numbers = collect($response->json('data'))->pluck('invoice_number')->all();
        // Owner with school_id=0 resolves to no active_school → sees all
        $this->assertContains('INV-MSG-001', $numbers);
        $this->assertContains('INV-SEK-001', $numbers);
    }

    /** (c) Students index for accountantA only returns Msingi students */
    public function test_student_index_scoped_to_accountants_school(): void
    {
        $token = $this->accountantA->createToken('t')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/students');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('last_name')->all();
        $this->assertContains('Msingi', $names);
        $this->assertNotContains('Sekondari', $names);
    }

    /** (d) Student promotion: new enrollment, same identity, old invoice preserved */
    public function test_student_promotion_creates_new_enrollment_preserves_identity(): void
    {
        $year2 = AcademicYear::where('school_id', $this->sekondari->id)->first();
        $classB = SchoolClass::where('school_id', $this->sekondari->id)->first();

        // Get the Msingi student
        $student = Student::where('last_name', 'Msingi')->first();
        $originalId = $student->id;

        // Promote: close old enrollment, open new one at Sekondari
        $student->enrollments()->where('school_id', $this->msingi->id)->update(['status' => 'transferred']);
        Enrollment::create([
            'student_id' => $student->id,
            'school_id' => $this->sekondari->id,
            'school_class_id' => $classB->id,
            'academic_year_id' => $year2->id,
            'admission_number' => 'SEK-99',
            'status' => 'active',
            'admitted_at' => '2024-06-01',
        ]);

        // Identity is preserved
        $this->assertDatabaseHas('students', ['id' => $originalId, 'last_name' => 'Msingi']);
        // Old invoice (Msingi) still exists
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-MSG-001', 'student_id' => $originalId]);
        // Student now has 2 enrollments
        $this->assertEquals(2, $student->fresh()->enrollments()->count());
        // Current enrollment is Sekondari
        $this->assertEquals('SEK-99', $student->fresh()->currentEnrollment->admission_number);
    }
}
