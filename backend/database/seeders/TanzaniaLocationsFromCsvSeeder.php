<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TanzaniaLocationsFromCsvSeeder extends Seeder
{
    public function run(): void
    {
        $directory = base_path('location-files');

        if (! is_dir($directory)) {
            $this->command->warn('location-files directory not found — skipping.');

            return;
        }

        $this->command->info('Truncating location tables…');

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');
        DB::unprepared('TRUNCATE TABLE places');
        DB::unprepared('TRUNCATE TABLE villages');
        DB::unprepared('TRUNCATE TABLE wards');
        DB::unprepared('TRUNCATE TABLE districts');
        DB::unprepared('TRUNCATE TABLE states');
        DB::unprepared('SET FOREIGN_KEY_CHECKS=1');

        // Verify truncate worked
        $remaining = DB::table('wards')->count();
        if ($remaining > 0) {
            $this->command->error("Truncate failed — {$remaining} wards still in DB. Aborting.");

            return;
        }

        // In-memory lookup maps so we avoid redundant DB reads per row
        $states = [];   // name => id
        $districts = [];   // "state_id:name" => id
        $wards = [];   // "district_id:name" => id
        $villages = [];   // "ward_id:name" => id

        $now = now()->toDateTimeString();

        // Bulk-insert buffers
        $stateBuf = [];
        $districtBuf = [];
        $wardBuf = [];
        $villageBuf = [];
        $placeBuf = [];

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.csv');
        sort($files);

        $this->command->info('Parsing '.count($files).' CSV files…');

        foreach ($files as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                continue;
            }

            fgetcsv($handle); // skip header

            while (($row = fgetcsv($handle)) !== false) {
                $row = array_pad($row, 8, null);

                [$region, , $district, , $ward, , $street, $place] = $row;

                $regionName = $this->normalize($region);
                $districtName = $this->normalize($district);
                $wardName = $this->normalize($ward);
                $streetName = $this->normalize($street);
                $placeName = $this->normalize($place);

                if (! $regionName || ! $districtName || ! $wardName) {
                    continue;
                }

                // ── State ────────────────────────────────────────────────────
                if (! array_key_exists($regionName, $states)) {
                    $stateBuf[] = ['name' => $regionName, 'country_code' => 'TZ', 'created_at' => $now, 'updated_at' => $now];
                    $states[$regionName] = null; // placeholder; real id assigned after flush
                }

                // ── District ─────────────────────────────────────────────────
                $dKey = $regionName.':'.$districtName;
                if (! array_key_exists($dKey, $districts)) {
                    $districtBuf[] = ['_region' => $regionName, 'name' => $districtName, 'created_at' => $now, 'updated_at' => $now];
                    $districts[$dKey] = null;
                }

                // ── Ward ─────────────────────────────────────────────────────
                $wKey = $dKey.':'.$wardName;
                if (! array_key_exists($wKey, $wards)) {
                    $wardBuf[] = ['_dkey' => $dKey, 'name' => $wardName, 'created_at' => $now, 'updated_at' => $now];
                    $wards[$wKey] = null;
                }

                // ── Village / Street ──────────────────────────────────────────
                if ($streetName) {
                    $vKey = $wKey.':'.$streetName;
                    if (! array_key_exists($vKey, $villages)) {
                        $villageBuf[] = ['_wkey' => $wKey, 'name' => $streetName, 'created_at' => $now, 'updated_at' => $now];
                        $villages[$vKey] = null;
                    }
                    // Place under village
                    if ($placeName) {
                        $placeBuf[] = ['_vkey' => $vKey, 'name' => $placeName, 'created_at' => $now, 'updated_at' => $now];
                    }
                } elseif ($placeName) {
                    // Urban ward: place IS the village
                    $vKey = $wKey.':'.$placeName;
                    if (! array_key_exists($vKey, $villages)) {
                        $villageBuf[] = ['_wkey' => $wKey, 'name' => $placeName, 'created_at' => $now, 'updated_at' => $now];
                        $villages[$vKey] = null;
                    }
                }
            }

            fclose($handle);
        }

        // ── Flush states ──────────────────────────────────────────────────────
        $this->command->info('Inserting states…');
        foreach (array_chunk($stateBuf, 500) as $chunk) {
            DB::table('states')->insert($chunk);
        }
        foreach (DB::table('states')->where('country_code', 'TZ')->get(['id', 'name']) as $s) {
            $states[$s->name] = $s->id;
        }

        // ── Flush districts ───────────────────────────────────────────────────
        $this->command->info('Inserting districts…');
        $distRows = [];
        foreach ($districtBuf as $d) {
            $stateId = $states[$d['_region']] ?? null;
            if (! $stateId) {
                continue;
            }
            $distRows[] = ['state_id' => $stateId, 'name' => $d['name'], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($distRows, 500) as $chunk) {
            DB::table('districts')->insert($chunk);
        }
        foreach (DB::table('districts')->get(['id', 'state_id', 'name']) as $d) {
            $regionName = array_search($d->state_id, $states);
            $districts[$regionName.':'.$d->name] = $d->id;
        }

        // ── Flush wards ───────────────────────────────────────────────────────
        $this->command->info('Inserting wards…');
        $wardRows = [];
        foreach ($wardBuf as $w) {
            $districtId = $districts[$w['_dkey']] ?? null;
            if (! $districtId) {
                continue;
            }
            $wardRows[] = ['lga_id' => $districtId, 'name' => $w['name'], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($wardRows, 500) as $chunk) {
            DB::table('wards')->insert($chunk);
        }
        foreach (DB::table('wards')->get(['id', 'lga_id', 'name']) as $w) {
            $dKey = array_search($w->lga_id, $districts);
            $wards[$dKey.':'.$w->name] = $w->id;
        }

        // ── Flush villages ────────────────────────────────────────────────────
        $this->command->info('Inserting villages…');
        $villageRows = [];
        foreach ($villageBuf as $v) {
            $wardId = $wards[$v['_wkey']] ?? null;
            if (! $wardId) {
                continue;
            }
            $villageRows[] = ['ward_id' => $wardId, 'name' => $v['name'], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($villageRows, 500) as $chunk) {
            DB::table('villages')->insert($chunk);
        }
        foreach (DB::table('villages')->get(['id', 'ward_id', 'name']) as $v) {
            $wKey = array_search($v->ward_id, $wards);
            $villages[$wKey.':'.$v->name] = $v->id;
        }

        // ── Flush places ──────────────────────────────────────────────────────
        if (! empty($placeBuf)) {
            $this->command->info('Inserting places…');
            $placeRows = [];
            foreach ($placeBuf as $p) {
                $villageId = $villages[$p['_vkey']] ?? null;
                if (! $villageId) {
                    continue;
                }
                $placeRows[] = ['village_id' => $villageId, 'name' => $p['name'], 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($placeRows, 500) as $chunk) {
                DB::table('places')->insert($chunk);
            }
        }

        $this->command->info('Done. States: '.count($states).', Districts: '.count($districts).', Wards: '.count($wards).', Villages: '.count($villages).'.');
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return ucwords(mb_strtolower($value, 'UTF-8'));
    }
}
