<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolNotification;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\AttendanceHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The attendance history: a day or a period by class, who was away, and each pupil's rate.
 * A late pupil counts as attending. Another school's registers must never show up.
 */
class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private SchoolClass $class;

    private SchoolClass $otherClass;

    /** @var array<string, Student> */
    private array $students = [];

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();   // no real SMS from a register being submitted
        foreach (['head_teacher', 'teacher_pri', 'academic_pri', 'accountant', 'parent'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $other = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Darasa la 5', 'sort_order' => 1]);
        $this->otherClass = SchoolClass::create(['school_id' => $other->id, 'name' => 'Beta class', 'sort_order' => 1]);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);

        foreach (['ali' => ['Ali', 'Juma'], 'baraka' => ['Baraka', 'Said'], 'chausiku' => ['Chausiku', 'Omari']] as $key => [$first, $last]) {
            $s = Student::create(['first_name' => $first, 'last_name' => $last, 'status' => 'active']);
            Enrollment::create(['student_id' => $s->id, 'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $year->id, 'admission_number' => "ALP/{$key}", 'status' => 'active', 'admitted_at' => now()]);
            $this->students[$key] = $s;
        }
        $outsider = Student::create(['first_name' => 'Other', 'last_name' => 'Pupil', 'status' => 'active']);

        $this->monday = now()->startOfWeek()->toDateString();
        // Monday..Friday: Ali always present; Baraka late on Tuesday; Chausiku absent Monday-Wednesday
        foreach (range(0, 4) as $i) {
            $day = now()->startOfWeek()->addDays($i)->toDateString();
            foreach (['ali' => 'present', 'baraka' => $i === 1 ? 'late' : 'present', 'chausiku' => $i < 3 ? 'absent' : 'present'] as $key => $status) {
                Attendance::create(['school_id' => $this->school->id, 'date' => $day, 'school_class_id' => $this->class->id, 'student_id' => $this->students[$key]->id, 'status' => $status, 'remarks' => $status === 'absent' && $i === 0 ? 'Sick' : null]);
            }
        }
        Attendance::create(['school_id' => $other->id, 'date' => $this->monday, 'school_class_id' => $this->otherClass->id, 'student_id' => $outsider->id, 'status' => 'absent']);
    }

    private function as(string $role): void
    {
        $u = User::factory()->create(['school_id' => $this->school->id]);
        $u->assignRole($role);
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('t')->plainTextToken);
    }

    private function friday(): string
    {
        return now()->startOfWeek()->addDays(4)->toDateString();
    }

    public function test_the_overview_adds_up_by_day_and_by_class_and_counts_late_as_attending(): void
    {
        $this->as('head_teacher');
        $data = $this->getJson("/api/attendance/history?from={$this->monday}&to={$this->friday()}")->assertOk()->json('data');

        $this->assertSame(['present' => 11, 'late' => 1, 'absent' => 3, 'records' => 15, 'rate' => 80], $data['totals']);
        $this->assertCount(5, $data['days']);
        $this->assertSame($this->friday(), $data['days'][0]['date'], 'newest day first');
        $monday = collect($data['days'])->firstWhere('date', $this->monday);
        $this->assertSame(['present' => 2, 'late' => 0, 'absent' => 1, 'total' => 3, 'rate' => 67], collect($monday)->only(['present', 'late', 'absent', 'total', 'rate'])->all());
        $this->assertCount(1, $data['classes'], "the other school's class is not listed");
        $this->assertSame('Darasa la 5', $data['classes'][0]['label']);
        $this->assertSame(5, $data['classes'][0]['days_marked']);
        $this->assertSame(0, $data['classes'][0]['days_missing']);
        $this->assertSame(80, $data['low_rate']);
    }

    public function test_a_day_without_a_register_is_reported_as_missing(): void
    {
        $this->as('head_teacher');
        $lastMonday = now()->startOfWeek()->subWeek();   // last week: nothing was recorded
        $data = $this->getJson('/api/attendance/history?from='.$lastMonday->toDateString().'&to='.$lastMonday->copy()->addDays(4)->toDateString())->assertOk()->json('data');
        $this->assertSame(0, $data['totals']['records']);
        $this->assertNull($data['totals']['rate']);
        $this->assertSame(5, $data['classes'][0]['days_missing'], 'five school days with no register');

        $one = $this->getJson('/api/attendance/history?from='.$lastMonday->toDateString().'&to='.$lastMonday->toDateString())->json('data');
        $this->assertSame(['Darasa la 5'], $one['not_marked'], 'a single day names the classes that did not take it');
    }

    public function test_who_was_away_with_remarks_and_only_this_schools_pupils(): void
    {
        $this->as('accountant');
        $rows = $this->getJson("/api/attendance/history/absentees?from={$this->monday}")->assertOk()->json('data.absentees');
        $this->assertSame(['Chausiku Omari'], array_column($rows, 'name'));
        $this->assertSame('Sick', $rows[0]['remarks']);

        $tuesday = now()->startOfWeek()->addDay()->toDateString();
        $rows = $this->getJson("/api/attendance/history/absentees?from={$tuesday}&to={$tuesday}")->json('data.absentees');
        $this->assertEqualsCanonicalizing(['Chausiku Omari' => 'absent', 'Baraka Said' => 'late'], array_column($rows, 'status', 'name'));
    }

    public function test_pupils_are_listed_weakest_first_with_a_low_flag(): void
    {
        $this->as('academic_pri');
        $data = $this->getJson("/api/attendance/history/pupils?from={$this->monday}&to={$this->friday()}")->assertOk()->json('data');

        $this->assertSame(['Chausiku Omari', 'Ali Juma', 'Baraka Said'], array_column($data['pupils'], 'name'), 'equal rates go alphabetically');
        $this->assertSame([40, 100, 100], array_column($data['pupils'], 'rate'));
        $this->assertSame([true, false, false], array_column($data['pupils'], 'low'));
        $this->assertSame(3, $data['pupils'][0]['absent']);
        $this->assertCount(3, $data['pupils'], "the other school's pupil is not here");
    }

    public function test_one_class_can_be_chosen_and_a_foreign_class_is_refused(): void
    {
        $this->as('head_teacher');
        $this->getJson("/api/attendance/history?from={$this->monday}&to={$this->monday}&school_class_id={$this->class->id}")->assertOk()->assertJsonCount(1, 'data.classes');
        $this->getJson("/api/attendance/history?school_class_id={$this->otherClass->id}")->assertNotFound();
    }

    public function test_the_period_is_limited_and_swapped_dates_are_put_right(): void
    {
        [$from, $to] = AttendanceHistoryService::clampRange('2024-01-01', '2026-06-30');
        $this->assertEquals(AttendanceHistoryService::MAX_DAYS - 1, $from->diffInDays($to));
        $this->assertSame('2026-06-30', $to->toDateString());

        [$from, $to] = AttendanceHistoryService::clampRange('2026-03-10', '2026-03-01');
        $this->assertSame(['2026-03-01', '2026-03-10'], [$from->toDateString(), $to->toDateString()]);
    }

    public function test_submitting_the_same_register_twice_updates_it_and_alerts_only_about_new_absences(): void
    {
        $this->as('teacher_pri');
        $day = now()->startOfWeek()->subWeek()->toDateString();   // a day nobody has recorded yet
        $post = fn (array $statuses) => $this->postJson('/api/attendance/bulk-mark', [
            'school_class_id' => $this->class->id, 'date' => $day,
            'records' => collect($statuses)->map(fn ($s, $k) => ['student_id' => $this->students[$k]->id, 'status' => $s])->values()->all(),
        ]);
        $alerts = fn () => SchoolNotification::where('type', 'absence_alert')->count();

        $this->getJson("/api/attendance/register?school_class_id={$this->class->id}&date={$day}")->assertJsonPath('submitted', false);

        $post(['ali' => 'present', 'baraka' => 'present', 'chausiku' => 'absent'])->assertOk()->assertJsonPath('updated', false)->assertJsonPath('absent_count', 1)->assertJsonPath('newly_absent', 1);
        $this->assertSame(1, $alerts());

        $this->getJson("/api/attendance/register?school_class_id={$this->class->id}&date={$day}")->assertJsonPath('submitted', true)->assertJsonStructure(['submitted_at']);

        // the same register again: updated, still three rows, nobody is alerted a second time
        $post(['ali' => 'present', 'baraka' => 'present', 'chausiku' => 'absent'])->assertOk()->assertJsonPath('updated', true)->assertJsonPath('absent_count', 1)->assertJsonPath('newly_absent', 0);
        $this->assertSame(1, $alerts(), 'no second alert for the same absence');
        $this->assertSame(3, Attendance::where('date', $day)->count(), 'no duplicate rows');

        // someone else becomes absent: only that pupil is announced
        $post(['ali' => 'present', 'baraka' => 'absent', 'chausiku' => 'absent'])->assertOk()->assertJsonPath('absent_count', 2)->assertJsonPath('newly_absent', 1);
        $this->assertSame(2, $alerts());
        $this->assertSame(3, Attendance::where('date', $day)->count());
    }

    public function test_only_the_roles_that_take_registers_can_read_the_history(): void
    {
        $this->as('parent');
        $this->getJson('/api/attendance/history')->assertForbidden();
        $this->getJson('/api/attendance/history/pupils')->assertForbidden();

        foreach (['teacher_pri', 'accountant', 'head_teacher'] as $role) {
            $this->as($role);
            $this->getJson('/api/attendance/history')->assertOk();
        }
    }
}
