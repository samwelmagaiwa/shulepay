<?php
namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder {
    public function run(): void {
        $primary = School::firstOrCreate(
            ['code' => 'SMP'],
            [
                'name'       => 'Magreth Primary School',
                'slug'       => 'magreth-primary',
                'level'      => 'primary',
                'is_active'  => true,
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
            ['code' => 'MMS'],
            [
                'name'       => 'Magrethmary Secondary School',
                'slug'       => 'magrethmary-secondary',
                'level'      => 'secondary',
                'is_active'  => true,
            ]
        );

        // Also update existing SEK record if it still has the old demo name
        School::where('code', 'SEK')
              ->where('name', 'Shule ya Sekondari Demo')
              ->update([
                  'name' => 'Margaret Mary Secondary School',
                  'code' => 'MMS',
                  'slug' => 'margaret-mary-secondary',
              ]);

        // Reload after potential updates
        $primary   = School::where('slug', 'like', '%magreth-primary%')->orWhere('code', 'SMP')->first() ?? $primary;
        $secondary = School::where('slug', 'like', '%margaret-mary%')->orWhere('code', 'MMS')->first() ?? $secondary;

        foreach ([$primary, $secondary] as $school) {
            $year = AcademicYear::firstOrCreate(
                ['school_id' => $school->id, 'name' => '2026'],
                [
                    'start_date' => '2026-01-15',
                    'end_date'   => '2026-11-30',
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

            $label    = $school->level->value === 'primary' ? 'Darasa' : 'Kidato';
            $maxGrade = $school->level->value === 'primary' ? 7 : 6;
            for ($i = 1; $i <= $maxGrade; $i++) {
                SchoolClass::firstOrCreate(
                    ['school_id' => $school->id, 'name' => "{$label} la {$i}"],
                    ['sort_order' => $i, 'capacity' => 45]
                );
            }

            // Pre-primary classes for primary school
            if ($school->level->value === 'primary') {
                foreach (['PP 1', 'PP 2'] as $i => $ppName) {
                    SchoolClass::firstOrCreate(
                        ['school_id' => $school->id, 'name' => $ppName],
                        ['sort_order' => $i, 'capacity' => 30]
                    );
                }
            }
        }
    }
}
