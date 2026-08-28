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

/**
 * The /ada-madeni list renders one row per student, so the API must paginate by
 * student. These pin the two properties that broke when it paginated by invoice:
 * a page holds per_page students' worth of rows, and a student's invoices are
 * never split across a page boundary.
 */
class InvoiceGroupByStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $studentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Darasa la 1', 'sort_order' => 1]);

        $this->admin = User::factory()->create(['school_id' => null]);
        $this->admin->assignRole('superadmin');

        $terms = [];
        foreach (range(1, 4) as $n) {
            $terms[] = Term::create([
                'academic_year_id' => $year->id, 'name' => "Muhula {$n}", 'number' => $n,
                'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => $n === 1,
            ]);
        }

        // 6 students x 4 term invoices = 24 invoices, but only 6 rendered rows.
        foreach (range(1, 6) as $i) {
            $student = Student::create(['first_name' => "Juma{$i}", 'last_name' => 'Msingi', 'status' => 'active']);
            Enrollment::create([
                'student_id' => $student->id, 'school_id' => $school->id,
                'school_class_id' => $class->id, 'academic_year_id' => $year->id,
                'admission_number' => "MSG-{$i}", 'status' => 'active', 'admitted_at' => '2024-01-15',
            ]);
            foreach ($terms as $t) {
                Invoice::withoutGlobalScope('school')->create([
                    'student_id' => $student->id, 'school_id' => $school->id,
                    'term_id' => $t->id, 'academic_year_id' => $year->id,
                    'invoice_number' => "INV-{$i}-{$t->number}", 'total_amount_cents' => 50000,
                    'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid',
                    'generated_at' => now(), 'generated_by' => $this->admin->id,
                ]);
            }
            $this->studentIds[] = $student->id;
        }
    }

    public function test_group_by_student_paginates_students_not_invoices(): void
    {
        $token = $this->admin->createToken('t')->plainTextToken;

        $res = $this->withToken($token)
            ->getJson('/api/invoices?group_by_student=1&per_page=2')
            ->assertOk();

        $this->assertSame(6, $res->json('meta.total'), 'total counts students, not invoices');
        $this->assertCount(2, collect($res->json('data'))->pluck('student.id')->unique(),
            'page holds exactly per_page students');
        $this->assertCount(8, $res->json('data'),
            'and every invoice belonging to them');
    }

    public function test_pages_do_not_overlap_or_drop_students(): void
    {
        $token = $this->admin->createToken('t')->plainTextToken;

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $data = $this->withToken($token)
                ->getJson("/api/invoices?group_by_student=1&per_page=2&page={$page}")
                ->assertOk()->json('data');
            $seen = array_merge($seen, collect($data)->pluck('student.id')->unique()->all());
        }

        sort($seen);
        $expected = $this->studentIds;
        sort($expected);
        $this->assertSame($expected, $seen, 'each student appears on exactly one page');
    }

    public function test_ungrouped_mode_still_paginates_invoices(): void
    {
        $token = $this->admin->createToken('t')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/invoices?per_page=2')->assertOk();

        $this->assertSame(24, $res->json('meta.total'));
        $this->assertCount(2, $res->json('data'));
    }
}
