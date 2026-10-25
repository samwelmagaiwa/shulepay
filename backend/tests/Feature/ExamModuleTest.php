<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The exam module end to end: set-up by the heads, marks by the assigned teacher, results and printed
 * documents, publishing to parents — and nothing leaking between schools.
 */
class ExamModuleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private School $other;

    private SchoolClass $class;

    private Term $term;

    /** @var array<string, Student> */
    private array $pupils = [];

    private Subject $math;

    private Subject $english;

    private User $head;

    private User $teacher;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();   // publishing texts the guardians; no real SMS
        foreach (['head_teacher', 'teacher_pri', 'accountant', 'parent', 'owner'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->school = School::create(['name' => 'Alpha', 'code' => 'ALP', 'slug' => 'alpha', 'level' => 'primary', 'is_active' => true]);
        $this->other = School::create(['name' => 'Beta', 'code' => 'BET', 'slug' => 'beta', 'level' => 'primary', 'is_active' => true]);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->term = Term::create(['academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true]);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Darasa la 5', 'sort_order' => 1]);

        foreach (['ali' => ['Ali', 'Juma'], 'zawadi' => ['Zawadi', 'Said']] as $key => [$first, $last]) {
            $s = Student::create(['first_name' => $first, 'last_name' => $last, 'status' => 'active']);
            Enrollment::create(['student_id' => $s->id, 'school_id' => $this->school->id, 'school_class_id' => $this->class->id, 'academic_year_id' => $year->id, 'admission_number' => "ALP/{$key}", 'status' => 'active', 'admitted_at' => now()]);
            $this->pupils[$key] = $s;
        }

        $this->head = $this->user('head_teacher');
        $this->teacher = $this->user('teacher_pri');
        $this->parent = $this->user('parent');
        $guardian = Guardian::create(['first_name' => 'Mzazi', 'last_name' => 'Juma', 'phone' => '0712000000', 'user_id' => $this->parent->id]);
        $guardian->students()->attach($this->pupils['ali']->id, ['relation' => 'father', 'is_primary' => true, 'receives_sms' => false]);

        $this->math = Subject::create(['school_id' => $this->school->id, 'name' => 'Hisabati', 'code' => 'MATH', 'is_active' => true]);
        $this->english = Subject::create(['school_id' => $this->school->id, 'name' => 'Kiingereza', 'code' => 'ENG', 'is_active' => true]);
    }

    private function user(string $role, ?School $school = null): User
    {
        $u = User::factory()->create(['school_id' => ($school ?? $this->school)->id]);
        $u->assignRole($role);

        return $u;
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($u->createToken('t')->plainTextToken);
    }

    private function createExam(array $extra = []): int
    {
        return $this->as($this->head)->postJson('/api/exams', $extra + [
            'name' => 'Terminal 1', 'type' => 'terminal', 'term_id' => $this->term->id, 'all_classes' => true,
            'out_of' => 100, 'require_comments' => false,
        ])->assertSuccessful()->json('data.id');
    }

    /** Subjects taught in the class, the teacher assigned, the exam open. */
    private function openExam(): int
    {
        $this->as($this->head)->putJson("/api/class-subjects/{$this->class->id}", ['subject_ids' => [$this->math->id, $this->english->id]])->assertOk();
        $this->as($this->head)->putJson("/api/teacher-assignments/{$this->teacher->id}", ['assignments' => [
            ['school_class_id' => $this->class->id, 'subject_id' => $this->math->id],
            ['school_class_id' => $this->class->id, 'subject_id' => $this->english->id],
        ]])->assertOk();
        $id = $this->createExam();
        $this->as($this->head)->postJson("/api/exams/{$id}/open")->assertOk();

        return $id;
    }

    private function enter(int $exam, Subject $subject, array $marksByPupil, ?User $by = null)
    {
        return $this->as($by ?? $this->teacher)->putJson("/api/exams/{$exam}/marks", [
            'school_class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'entries' => collect($marksByPupil)->map(fn ($m, $k) => ['student_id' => $this->pupils[$k]->id, 'marks' => $m])->values()->all(),
        ]);
    }

    private function pages(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page(?!s)\b/', $pdf);
    }

    public function test_only_the_heads_set_exams_up(): void
    {
        $this->as($this->teacher)->postJson('/api/exams', ['name' => 'X', 'type' => 'test', 'term_id' => $this->term->id, 'all_classes' => true])->assertForbidden();
        $this->as($this->user('accountant'))->getJson('/api/exams')->assertForbidden();

        $id = $this->createExam();
        $this->as($this->teacher)->getJson('/api/exams')->assertOk()->assertJsonFragment(['id' => $id]);
        $this->assertDatabaseHas('exams', ['id' => $id, 'school_id' => $this->school->id, 'status' => 'draft']);
    }

    public function test_an_exam_name_is_unique_within_its_term(): void
    {
        $this->createExam();
        $this->as($this->head)->postJson('/api/exams', ['name' => 'Terminal 1', 'type' => 'terminal', 'term_id' => $this->term->id, 'all_classes' => true])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_a_teacher_enters_marks_only_for_what_they_are_assigned(): void
    {
        $this->as($this->head)->putJson("/api/class-subjects/{$this->class->id}", ['subject_ids' => [$this->math->id, $this->english->id]])->assertOk();
        $id = $this->createExam();
        $this->as($this->head)->postJson("/api/exams/{$id}/open")->assertOk();

        // not assigned yet
        $this->enter($id, $this->math, ['ali' => 70, 'zawadi' => 55])->assertStatus(403);

        $this->as($this->head)->putJson("/api/teacher-assignments/{$this->teacher->id}", ['assignments' => [
            ['school_class_id' => $this->class->id, 'subject_id' => $this->math->id],
        ]])->assertOk();

        $this->enter($id, $this->math, ['ali' => 70, 'zawadi' => 55])->assertOk();
        $this->enter($id, $this->english, ['ali' => 60, 'zawadi' => 50])->assertStatus(403);   // assigned to Maths only
        $this->enter($id, $this->math, ['ali' => 101, 'zawadi' => 55])->assertStatus(422);       // more than the exam total
        $this->assertDatabaseCount('exam_results', 2);
    }

    public function test_results_are_ranked_and_the_documents_print(): void
    {
        $id = $this->openExam();
        $this->enter($id, $this->math, ['ali' => 80, 'zawadi' => 60])->assertOk();
        $this->enter($id, $this->english, ['ali' => 70, 'zawadi' => 90])->assertOk();

        $res = $this->as($this->head)->getJson("/api/exams/{$id}/class-results?school_class_id={$this->class->id}")->assertOk()->json('data');
        $rows = collect($res['rows']);
        $this->assertSame(75.0, (float) $rows->firstWhere('full_name', 'Ali Juma')['average']);
        $this->assertSame(75.0, (float) $rows->firstWhere('full_name', 'Zawadi Said')['average']);

        $card = $this->as($this->head)->getJson("/api/exams/{$id}/students/{$this->pupils['ali']->id}/report-card")->assertOk()->json('data');
        $this->assertSame('Ali Juma', $card['student']['full_name']);
        $this->assertCount(2, $card['subjects']);

        $one = $this->as($this->head)->get("/api/exams/{$id}/students/{$this->pupils['ali']->id}/report-card/pdf?school_class_id={$this->class->id}");
        $one->assertOk();
        $this->assertSame('application/pdf', $one->headers->get('Content-Type'));
        $this->assertSame(1, $this->pages($one->getContent()));

        $all = $this->as($this->head)->get("/api/exams/{$id}/report-cards/pdf?school_class_id={$this->class->id}");
        $all->assertOk();
        $this->assertSame(2, $this->pages($all->getContent()), 'one report card per pupil, each on its own page');

        $sheet = $this->as($this->head)->get("/api/exams/{$id}/class-results/pdf?school_class_id={$this->class->id}");
        $sheet->assertOk();
        $this->assertStringContainsString('%PDF', substr($sheet->getContent(), 0, 10));
    }

    public function test_parents_see_results_only_after_they_are_published_and_only_their_own_child(): void
    {
        $id = $this->openExam();
        $this->enter($id, $this->math, ['ali' => 80, 'zawadi' => 60])->assertOk();
        $this->enter($id, $this->english, ['ali' => 70, 'zawadi' => 90])->assertOk();

        $ali = $this->pupils['ali']->id;
        $this->as($this->parent)->getJson("/api/parent/exam-results?student_id={$ali}")->assertOk()->assertJsonCount(0, 'data');

        $this->as($this->head)->postJson("/api/exams/{$id}/publish")->assertOk();
        $this->assertSame('published', Exam::find($id)->status);

        $list = $this->as($this->parent)->getJson("/api/parent/exam-results?student_id={$ali}")->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->as($this->parent)->getJson("/api/parent/exam-results/{$id}?student_id={$ali}")->assertOk()->assertJsonPath('data.student.full_name', 'Ali Juma');
        $pdf = $this->as($this->parent)->get("/api/parent/exam-results/{$id}/pdf?student_id={$ali}");
        $pdf->assertOk();
        $this->assertSame(1, $this->pages($pdf->getContent()));

        // someone else's child is refused
        $zawadi = $this->pupils['zawadi']->id;
        $this->as($this->parent)->getJson("/api/parent/exam-results?student_id={$zawadi}")->assertForbidden();

        // published results are locked
        $this->enter($id, $this->math, ['ali' => 99])->assertStatus(409);
        $this->as($this->head)->deleteJson("/api/exams/{$id}")->assertStatus(409);
    }

    public function test_one_school_never_sees_anothers_exams_or_classes(): void
    {
        $id = $this->createExam();

        $otherHead = $this->user('head_teacher', $this->other);
        $this->as($otherHead)->getJson('/api/exams')->assertOk()->assertJsonMissing(['id' => $id]);
        $this->as($otherHead)->getJson("/api/exams/{$id}")->assertNotFound();
        $this->as($otherHead)->putJson("/api/class-subjects/{$this->class->id}", ['subject_ids' => []])->assertNotFound();
    }

    public function test_all_schools_mode_does_not_open_other_schools_exams_to_a_school_head(): void
    {
        $mine = $this->createExam();
        $foreign = Exam::withoutGlobalScopes()->create([
            'school_id' => $this->other->id, 'term_id' => $this->term->id, 'academic_year_id' => $this->term->academic_year_id,
            'name' => 'Beta exam', 'type' => 'terminal', 'status' => 'open', 'all_classes' => true, 'out_of' => 100,
        ])->id;

        $h = ['X-School-Id' => '0'];   // "all schools": the automatic school scope is switched off
        $list = $this->as($this->head)->withHeaders($h)->getJson('/api/exams')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$mine], array_column($list, 'id'));
        $this->as($this->head)->withHeaders($h)->getJson("/api/exams/{$foreign}")->assertNotFound();
        $this->as($this->head)->withHeaders($h)->postJson("/api/exams/{$foreign}/open")->assertNotFound();
        $this->as($this->head)->withHeaders($h)->deleteJson("/api/exams/{$foreign}")->assertNotFound();
        $this->assertDatabaseHas('exams', ['id' => $foreign]);
    }

    public function test_the_readiness_guide_and_the_grading_scale_load(): void
    {
        $this->as($this->head)->getJson('/api/exams/readiness')->assertOk();
        $this->as($this->head)->getJson('/api/grading-scale')->assertOk();
        $this->as($this->head)->getJson('/api/subjects')->assertOk()->assertJsonFragment(['name' => 'Hisabati']);
    }
}
