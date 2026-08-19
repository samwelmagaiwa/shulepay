<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds only the minimum required for a working system:
 *   - Owner and accountant user accounts
 *   - Fee structures for each school class
 *
 * All student, guardian, invoice, and payment data is imported
 * via `php artisan import:students-excel` from real source data.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $msingi = School::where('code', 'MSG')->first();
        $sekondari = School::where('code', 'SEK')->first();

        // ── Admin accounts ─────────────────────────────────────────────────────
        $msingiId = $msingi ? $msingi->id : School::first()?->id;

        $superadmin = User::firstOrCreate(
            ['email' => 'super@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'school_id' => $msingiId,
            ]
        );
        $superadmin->syncRoles(['superadmin']);

        $owner = User::firstOrCreate(
            ['email' => 'owner@gmail.com'],
            [
                'name' => 'Mmiliki Mkuu',
                'password' => Hash::make('12345678'),
                'school_id' => $msingiId,
            ]
        );
        $owner->syncRoles(['owner']);

        $accountant = User::firstOrCreate(
            ['email' => 'accountant@gmail.com'],
            [
                'name' => 'Muhasibu Mkuu',
                'password' => Hash::make('12345678'),
                'school_id' => $msingiId,
            ]
        );
        $accountant->syncRoles(['accountant']);

        // ── Fee structures ─────────────────────────────────────────────────────
        if ($msingi) {
            $this->seedFeeStructures($msingi, $accountant);
        }
        if ($sekondari) {
            $this->seedFeeStructures($sekondari, $accountant);
        }
    }

    private function seedFeeStructures(School $school, User $accountant): void
    {
        $year = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        if (!$year)
            return;

        $term1 = Term::where('academic_year_id', $year->id)->where('number', 1)->first();
        if (!$term1)
            return;

        $feeItems = $school->level->value === 'primary'
            ? [
                ['name' => 'Ada ya Masomo', 'category' => 'masomo', 'amount_cents' => 45000000],
                ['name' => 'Chakula', 'category' => 'chakula', 'amount_cents' => 15000000],
                ['name' => 'Vitabu', 'category' => 'nyingine', 'amount_cents' => 8000000],
            ]
            : [
                ['name' => 'Ada ya Masomo', 'category' => 'masomo', 'amount_cents' => 80000000],
                ['name' => 'Bweni', 'category' => 'bweni', 'amount_cents' => 30000000],
                ['name' => 'Chakula', 'category' => 'chakula', 'amount_cents' => 25000000],
                ['name' => 'Usafiri', 'category' => 'usafiri', 'amount_cents' => 10000000],
            ];

        foreach ($school->schoolClasses()->orderBy('sort_order')->get() as $i => $class) {
            $structure = FeeStructure::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'school_class_id' => $class->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term1->id,
                ],
                [
                    'name' => "Ada — {$class->name}, Muhula 1, {$year->name}",
                    'is_active' => true,
                ]
            );

            if ($structure->wasRecentlyCreated) {
                foreach ($feeItems as $idx => $item) {
                    $structure->feeItems()->create(array_merge($item, [
                        'is_optional' => false,
                        'sort_order' => $idx,
                    ]));
                }
            }
        }
    }
}
