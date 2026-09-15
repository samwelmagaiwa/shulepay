<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Reporting\StudentGenderBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The gender chart previously plotted payment-method counts (cash, M-Pesa, bank,
 * cheque) under gender labels. These tests pin that it now counts students, over
 * exactly the population the All Students card uses.
 */
class StudentGenderBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicYear $year;

    private SchoolClass $class;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary']);
        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'STANDARD ONE', 'sort_order' => 3]);
    }

    /**
     * Gender is written straight to the column: the SQLite test schema still
     * carries the pre-migration me/ke CHECK constraint, and this also lets the
     * test store the legacy and unrecognised values the service must handle.
     */
    private function student(?string $gender, string $status = 'active', ?School $school = null): Student
    {
        $school ??= $this->school;
        $student = Student::create(['first_name' => 'S'.(++$this->seq), 'last_name' => 'Test', 'status' => 'active']);
        DB::statement('PRAGMA ignore_check_constraints = ON');
        DB::table('students')->where('id', $student->id)->update(['gender' => $gender]);
        DB::statement('PRAGMA ignore_check_constraints = OFF');

        Enrollment::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'school_class_id' => $this->class->id, 'academic_year_id' => $this->year->id,
            'admission_number' => 'ADM-'.$this->seq, 'status' => $status, 'admitted_at' => '2026-01-10',
        ]);

        return $student;
    }

    private function breakdown(): array
    {
        return app(StudentGenderBreakdown::class)->for($this->school->id);
    }

    public function test_students_are_counted_by_gender(): void
    {
        $this->student('male');
        $this->student('male');
        $this->student('female');
        $this->student(null);

        $this->assertSame(
            ['male' => 2, 'female' => 1, 'unspecified' => 1, 'total' => 4],
            $this->breakdown()
        );
    }

    public function test_legacy_codes_and_unknown_values_are_bucketed_not_dropped(): void
    {
        $this->student('me');
        $this->student('KE');
        $this->student('');
        $this->student('other');

        $b = $this->breakdown();

        $this->assertSame(1, $b['male']);
        $this->assertSame(1, $b['female']);
        $this->assertSame(2, $b['unspecified']);
        $this->assertSame(4, $b['total']);
    }

    /** The slices must add up to the All Students card, or the two disagree on screen. */
    public function test_total_matches_the_all_students_population(): void
    {
        $this->student('male');
        $this->student('female');
        $this->student('female', 'dropped');                 // not active
        $this->student('male')->delete();                    // soft-deleted
        $other = School::create(['name' => 'Sek', 'code' => 'SEK', 'slug' => 'sek', 'level' => 'secondary']);
        $this->student('male', 'active', $other);            // another school

        $allStudents = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereHas('student')
            ->where('school_id', $this->school->id)
            ->distinct('student_id')
            ->count('student_id');

        $b = $this->breakdown();

        $this->assertSame(2, $b['total']);
        $this->assertSame($allStudents, $b['total']);
    }

    public function test_a_student_with_two_active_enrollments_is_counted_once(): void
    {
        $student = $this->student('female');
        // One enrollment per student/school/year is enforced by a unique index,
        // so the realistic double is an active row left over from another year.
        $nextYear = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2027',
            'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_current' => false,
        ]);
        Enrollment::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $this->school->id,
            'school_class_id' => $this->class->id, 'academic_year_id' => $nextYear->id,
            'admission_number' => 'ADM-DUP', 'status' => 'active', 'admitted_at' => '2026-02-01',
        ]);

        $this->assertSame(1, $this->breakdown()['female']);
    }
}
