<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The collections report used to hide its debtors behind a "N debtors" dropdown
 * that joined each name and their unpaid terms into one string. The table now
 * gives the name and the terms a column each, which needs them delivered apart.
 *
 * The joined `debtors` strings stay in the payload because the xlsx export packs
 * them into a single cell — dropping them would have broken that export.
 */
class CollectionsDebtorsColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_debtors_are_returned_with_name_and_terms_separated(): void
    {
        foreach (['accountant', 'owner', 'parent', 'superadmin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $school = School::create([
            'name' => 'Msingi', 'code' => 'MSG', 'slug' => 'msingi', 'level' => 'primary',
        ]);
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
        ]);
        $term = Term::create([
            'academic_year_id' => $year->id, 'name' => 'Muhula 1', 'number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-04-30', 'is_current' => true,
        ]);
        $class = SchoolClass::create([
            'school_id' => $school->id, 'name' => 'Darasa la 1', 'sort_order' => 1,
        ]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $user->assignRole('accountant');

        $student = Student::create([
            'first_name' => 'Juma', 'last_name' => 'Mfungo', 'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'school_class_id' => $class->id, 'academic_year_id' => $year->id,
            'admission_number' => 'MSG-1', 'status' => 'active', 'admitted_at' => '2026-01-15',
        ]);

        // Partly paid, so the invoice stays a debt and the student shows up as
        // a debtor on the day the payment landed.
        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id, 'school_id' => $school->id,
            'term_id' => $term->id, 'academic_year_id' => $year->id,
            'invoice_number' => 'INV-1', 'total_amount_cents' => 100000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'partial',
            'generated_at' => now(), 'generated_by' => $user->id,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id, 'student_id' => $student->id,
            'school_id' => $school->id, 'amount_cents' => 40000,
            'method' => 'cash', 'paid_at' => now(), 'recorded_by' => $user->id,
        ]);

        $rows = $this->withToken($user->createToken('t')->plainTextToken)
            ->getJson('/api/reports/collections?from='.now()->subDay()->toDateString()
                .'&to='.now()->addDay()->toDateString())
            ->assertOk()
            ->json('rows');

        $withDebtors = collect($rows)->firstWhere(fn ($r) => ! empty($r['debtors_detailed']));
        $this->assertNotNull($withDebtors, 'the partly paid invoice should appear as a debt');

        $entry = $withDebtors['debtors_detailed'][0];
        // Student::fullName() interpolates the middle name unconditionally, so a
        // student without one carries a double space. Pre-existing and visible on
        // receipts too; asserted as-is here rather than quietly worked around.
        $this->assertSame('Juma  Mfungo', $entry['student_name']);
        $this->assertSame('Muhula 1', $entry['terms']);

        // 100,000 invoiced less the 40,000 paid — the column has to reconcile
        // with the day's Total Debt, not restate the invoice total.
        $this->assertSame(60000, $entry['balance_cents']);
        $this->assertSame($withDebtors['total_debt_cents'], $entry['balance_cents']);

        // The joined form must survive for the spreadsheet export.
        $this->assertSame('Juma  Mfungo (Muhula 1)', $withDebtors['debtors'][0]);
    }
}
