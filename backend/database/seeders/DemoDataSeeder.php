<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\InventoryItem;
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
        $schools = School::orderBy('id')->get();
        $msingi = $schools->first();
        $sekondari = $schools->count() > 1 ? $schools->get(1) : null;

        // ── Admin accounts ─────────────────────────────────────────────────────
        $msingiId = $msingi?->id ?? School::first()?->id;

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
            $this->seedAssets($msingi, $accountant);
            $this->seedInventory($msingi);
        }
        if ($sekondari) {
            $this->seedFeeStructures($sekondari, $accountant);
            $this->seedAssets($sekondari, $accountant);
            $this->seedInventory($sekondari);
        }
    }

    private function seedAssets(School $school, User $accountant): void
    {
        $assets = [
            ['name' => 'Kompyuta za Wanafunzi', 'category' => 'technology',  'quantity' => 20, 'purchase_cost_cents' => 120_000_00, 'condition' => 'good',      'status' => 'in_use',      'location' => 'Maabara ya Kompyuta', 'depreciation_method' => 'straight_line',    'useful_life_years' => 5,  'salvage_value_cents' => 10_000_00, 'purchase_date' => '2023-01-15'],
            ['name' => 'Projekta ya Darasa',    'category' => 'technology',  'quantity' => 5,  'purchase_cost_cents' => 85_000_00,  'condition' => 'excellent', 'status' => 'in_use',      'location' => 'Madarasa',            'depreciation_method' => 'straight_line',    'useful_life_years' => 6,  'salvage_value_cents' => 5_000_00,  'purchase_date' => '2023-06-10'],
            ['name' => 'Meza za Walimu',        'category' => 'furniture',   'quantity' => 15, 'purchase_cost_cents' => 18_000_00,  'condition' => 'good',      'status' => 'in_use',      'location' => 'Ofisi ya Walimu',     'depreciation_method' => 'straight_line',    'useful_life_years' => 10, 'salvage_value_cents' => 2_000_00,  'purchase_date' => '2022-03-01'],
            ['name' => 'Viti vya Wanafunzi',    'category' => 'furniture',   'quantity' => 200, 'purchase_cost_cents' => 3_500_00,   'condition' => 'fair',      'status' => 'in_use',      'location' => 'Madarasa',            'depreciation_method' => 'straight_line',    'useful_life_years' => 8,  'salvage_value_cents' => 500_00,    'purchase_date' => '2021-07-20'],
            ['name' => 'Basi la Shule',         'category' => 'vehicles',    'quantity' => 1,  'purchase_cost_cents' => 4_500_000_00, 'condition' => 'good',     'status' => 'in_use',      'location' => 'Gereji',              'depreciation_method' => 'reducing_balance', 'useful_life_years' => 10, 'salvage_value_cents' => 500_000_00, 'purchase_date' => '2022-09-05', 'depreciation_rate' => 20],
            ['name' => 'Jenereta',              'category' => 'equipment',   'quantity' => 1,  'purchase_cost_cents' => 380_000_00, 'condition' => 'good',      'status' => 'in_use',      'location' => 'Nyumba ya Jenereta',  'depreciation_method' => 'straight_line',    'useful_life_years' => 8,  'salvage_value_cents' => 30_000_00, 'purchase_date' => '2023-02-28'],
            ['name' => 'Vifaa vya Michezo',     'category' => 'sports',      'quantity' => 1,  'purchase_cost_cents' => 45_000_00,  'condition' => 'fair',      'status' => 'in_use',      'location' => 'Stoo ya Michezo',     'depreciation_method' => 'straight_line',    'useful_life_years' => 3,  'salvage_value_cents' => 5_000_00,  'purchase_date' => '2023-08-01'],
            ['name' => 'Printa ya Ofisi',       'category' => 'technology',  'quantity' => 2,  'purchase_cost_cents' => 65_000_00,  'condition' => 'fair',      'status' => 'under_repair', 'location' => 'Ofisi Kuu',           'depreciation_method' => 'straight_line',    'useful_life_years' => 5,  'salvage_value_cents' => 5_000_00,  'purchase_date' => '2022-11-15'],
        ];

        $prefix = strtoupper($school->code ?? 'AST').'-AST-';

        foreach ($assets as $i => $data) {
            $tag = $prefix.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
            InventoryItem::firstOrCreate(
                ['asset_tag' => $tag, 'school_id' => $school->id, 'type' => 'fixed_asset'],
                array_merge($data, [
                    'type' => 'fixed_asset',
                    'asset_tag' => $tag,
                    'school_id' => $school->id,
                    'registered_by' => $accountant->id,
                    'registered_at' => now()->subDays(rand(30, 400)),
                    'funding_source' => 'fees',
                    'is_active' => true,
                ])
            );
        }
    }

    private function seedInventory(School $school): void
    {
        $items = [
            ['name' => 'Chaki (Pakiti)',         'unit' => 'pakiti',  'quantity' => 50,  'reorder_level' => 10, 'unit_cost_cents' => 2_500_00],
            ['name' => 'Karatasi A4 (Rimi)',     'unit' => 'rimi',    'quantity' => 30,  'reorder_level' => 5,  'unit_cost_cents' => 18_000_00],
            ['name' => 'Kalamu za Wino (Doz)',   'unit' => 'doz',     'quantity' => 25,  'reorder_level' => 5,  'unit_cost_cents' => 4_800_00],
            ['name' => 'Vitabu vya Kumbukumbu',  'unit' => 'kipande', 'quantity' => 100, 'reorder_level' => 20, 'unit_cost_cents' => 1_500_00],
            ['name' => 'Sabuni ya Kuoshea',      'unit' => 'kilo',    'quantity' => 20,  'reorder_level' => 5,  'unit_cost_cents' => 2_000_00],
            ['name' => 'Mafuta ya Jenereta (L)', 'unit' => 'lita',    'quantity' => 100, 'reorder_level' => 20, 'unit_cost_cents' => 3_200_00],
            ['name' => 'Dawa ya Kusafisha',      'unit' => 'chupa',   'quantity' => 15,  'reorder_level' => 3,  'unit_cost_cents' => 4_500_00],
            ['name' => 'Glavu za Mpira (Jozi)',  'unit' => 'jozi',    'quantity' => 5,   'reorder_level' => 3,  'unit_cost_cents' => 1_200_00],
        ];

        foreach ($items as $data) {
            InventoryItem::firstOrCreate(
                ['school_id' => $school->id, 'name' => $data['name']],
                array_merge($data, ['school_id' => $school->id, 'is_active' => true])
            );
        }
    }

    private function seedFeeStructures(School $school, User $accountant): void
    {
        $year = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first();
        if (! $year) {
            return;
        }

        $term1 = Term::where('academic_year_id', $year->id)->where('number', 1)->first();
        if (! $term1) {
            return;
        }

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
