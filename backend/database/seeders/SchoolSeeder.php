<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $primary = School::firstOrCreate(
            ['code' => 'SMP'],
            [
                'name' => 'Magreth Primary School',
                'slug' => 'magreth-primary',
                'level' => 'primary',
                'is_active' => true,
            ]
        );

        // Also update existing MSG record if it still has the old demo name
        School::where('code', 'MSG')
            ->where('name', 'Shule ya Msingi Demo')
            ->update([
                'name' => 'St. Margaret Primary School',
                'code' => 'SMP',
                'slug' => 'st-margaret-primary',
            ]);

        $secondary = School::firstOrCreate(
            ['code' => 'MGRTHMR'],
            [
                'name' => 'Magrethmary Secondary School',
                'slug' => 'magrethmary-secondary',
                'level' => 'secondary',
                'is_active' => true,
            ]
        );

        // Migrate old MMS code to MGRTHMR
        School::where('code', 'MMS')->update(['code' => 'MGRTHMR']);
        School::where('code', 'SEK')
            ->where('name', 'Shule ya Sekondari Demo')
            ->update([
                'name' => 'Margaret Mary Secondary School',
                'code' => 'MGRTHMR',
                'slug' => 'margaret-mary-secondary',
            ]);

        // Reload after potential updates
        $primary = School::where('slug', 'like', '%magreth-primary%')->orWhere('code', 'SMP')->first() ?? $primary;
        $secondary = School::where('slug', 'like', '%magrethmary-secondary%')->orWhere('code', 'MGRTHMR')->first() ?? $secondary;

        foreach ([$primary, $secondary] as $school) {
            $year = AcademicYear::firstOrCreate(
                ['school_id' => $school->id, 'name' => '2026'],
                [
                    'start_date' => '2026-01-15',
                    'end_date' => '2026-11-30',
                    'is_current' => true,
                ]
            );

            // Also mark any older year as not current
            AcademicYear::where('school_id', $school->id)
                ->where('id', '!=', $year->id)
                ->update(['is_current' => false]);

            foreach ([
                [1, 'Muhula wa Kwanza', '2026-01-15', '2026-04-10', true],
                [2, 'Muhula wa Pili',   '2026-05-04', '2026-08-14', false],
                [3, 'Muhula wa Tatu',   '2026-09-07', '2026-11-27', false],
            ] as [$num, $name, $start, $end, $current]) {
                Term::firstOrCreate(
                    ['academic_year_id' => $year->id, 'number' => $num],
                    ['name' => $name, 'start_date' => $start, 'end_date' => $end, 'is_current' => $current]
                );
            }

            // Primary classes are worded (STANDARD ONE), matching the existing
            // secondary naming (FORM ONE) and the rename applied to live data.
            // Seeding the old "Darasa la N" here would leave fresh installs on a
            // naming the rest of the app no longer produces.
            $ordinals = [1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR',
                5 => 'FIVE', 6 => 'SIX', 7 => 'SEVEN'];

            $isPrimary = $school->level->value === 'primary';
            $label = $isPrimary ? 'STANDARD' : 'Kidato';
            $maxGrade = $isPrimary ? 7 : 6;
            for ($i = 1; $i <= $maxGrade; $i++) {
                $name = $isPrimary ? "STANDARD {$ordinals[$i]}" : "{$label} la {$i}";
                SchoolClass::firstOrCreate(
                    ['school_id' => $school->id, 'name' => $name],
                    // Pre-primary occupies 1-2, so standards start at 3 — the
                    // same offset the sort_order migration applies.
                    ['sort_order' => $isPrimary ? $i + 2 : $i, 'capacity' => 45]
                );
            }

            // Pre-primary classes for primary school
            if ($isPrimary) {
                foreach (['PP ONE', 'PP TWO'] as $i => $ppName) {
                    SchoolClass::firstOrCreate(
                        ['school_id' => $school->id, 'name' => $ppName],
                        ['sort_order' => $i + 1, 'capacity' => 30]
                    );
                }
            }
        }
    }
}
