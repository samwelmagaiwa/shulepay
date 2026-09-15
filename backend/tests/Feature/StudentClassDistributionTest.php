<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Reporting\StudentClassDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The class chart guessed age groups from class-name spellings and matched
 * nothing for FORM ONE…FORM FOUR, so a secondary school showed no data. These
 * tests pin that real classes come back, by name, in school order.
 */
class StudentClassDistributionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private AcademicYear $year;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Sekondari', 'code' => 'SEK', 'slug' => 'sek', 'level' => 'secondary']);
        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
    }

    private function enrol(SchoolClass $class, string $status = 'active', ?School $school = null): Student
    {
        $student = Student::create(['first_name' => 'S'.(++$this->seq), 'last_name' => 'Test', 'status' => 'active']);
        Enrollment::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => ($school ?? $this->school)->id,
            'school_class_id' => $class->id, 'academic_year_id' => $this->year->id,
            'admission_number' => 'ADM-'.$this->seq, 'status' => $status, 'admitted_at' => '2026-01-10',
        ]);

        return $student;
    }

    /** The exact case that showed "Hakuna Data": worded secondary class names. */
    public function test_worded_secondary_classes_are_returned(): void
    {
        $one = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'FORM ONE', 'sort_order' => 999]);
        $two = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'FORM TWO', 'sort_order' => 999]);
        $this->enrol($one);
        $this->enrol($one);
        $this->enrol($two);

        $rows = app(StudentClassDistribution::class)->for($this->school->id);

        $this->assertSame(['FORM ONE', 'FORM TWO'], array_column($rows, 'class_name'));
        $this->assertSame([2, 1], array_column($rows, 'students'));
    }

    /** Equal sort_order must fall back to creation order, not name (FORM FOUR < FORM ONE). */
    public function test_ties_on_sort_order_keep_creation_order(): void
    {
        foreach (['FORM ONE', 'FORM TWO', 'FORM THREE', 'FORM FOUR'] as $name) {
            $this->enrol(SchoolClass::create(['school_id' => $this->school->id, 'name' => $name, 'sort_order' => 999]));
        }

        $rows = app(StudentClassDistribution::class)->for($this->school->id);

        $this->assertSame(['FORM ONE', 'FORM TWO', 'FORM THREE', 'FORM FOUR'], array_column($rows, 'class_name'));
    }

    public function test_only_active_non_deleted_students_of_this_school_count(): void
    {
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'FORM ONE', 'sort_order' => 1]);
        $this->enrol($class);
        $this->enrol($class, 'dropped');
        $this->enrol($class)->delete();
        $other = School::create(['name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msg', 'level' => 'primary']);
        $this->enrol($class, 'active', $other);

        $rows = app(StudentClassDistribution::class)->for($this->school->id);

        $this->assertSame(1, $rows[0]['students']);
    }

    public function test_a_school_with_no_students_returns_an_empty_list(): void
    {
        $this->assertSame([], app(StudentClassDistribution::class)->for($this->school->id));
    }
}
