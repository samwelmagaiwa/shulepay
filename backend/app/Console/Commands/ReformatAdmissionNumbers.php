<?php
namespace App\Console\Commands;

use App\Enums\SchoolLevel;
use App\Models\Enrollment;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReformatAdmissionNumbers extends Command
{
    protected $signature = 'admissions:reformat {--dry-run : Preview changes without saving}';
    protected $description = 'Reformat old MSG-{n} style admission numbers to PRM/SEC/CODE/NNNN/YEAR format';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '🔍 DRY RUN — no changes will be saved.' : '✏️  Reformatting admission numbers...');

        $schools = School::all()->keyBy('id');

        // Catch: old style (MSG-1, SEK-1) AND previously migrated but wrong-code style (PRM/MSG/…, SEC/SEK/…)
        $enrollments = Enrollment::where(function ($q) {
            $q->whereRaw("admission_number REGEXP '^[A-Z]+-[0-9]+$'")    // old: MSG-1
                ->orWhereRaw("admission_number NOT REGEXP '^(PRM|SEC)/[A-Z]+/[0-9]{4}/[0-9]{4}$'"); // anything not matching new format
        })->get();


        if ($enrollments->isEmpty()) {
            $this->info('No old-format admission numbers found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$enrollments->count()} enrollment(s) to update.");

        // Group by school so we can assign sequential numbers per school per year
        $grouped = $enrollments->groupBy('school_id');

        DB::transaction(function () use ($grouped, $schools, $dryRun) {
            foreach ($grouped as $schoolId => $schoolEnrollments) {
                $school = $schools[$schoolId] ?? null;
                if (!$school) {
                    $this->warn("  School ID {$schoolId} not found — skipping.");
                    continue;
                }

                $prefix = $school->level === SchoolLevel::Secondary ? 'SEC' : 'PRM';
                $code = strtoupper($school->code);

                // Sort by old numeric suffix so we preserve original order
                $sorted = $schoolEnrollments->sortBy(function ($e) {
                    if (preg_match('/(\d+)$/', $e->admission_number, $m)) {
                        return (int) $m[1];
                    }
                    return 0;
                })->values();

                // Track sequences per year
                $seqByYear = [];

                foreach ($sorted as $enrollment) {
                    // Use admitted_at year if available, else created_at year
                    $year = $enrollment->admitted_at
                        ? \Carbon\Carbon::parse($enrollment->admitted_at)->year
                        : \Carbon\Carbon::parse($enrollment->created_at)->year;

                    $seqByYear[$year] = ($seqByYear[$year] ?? 0) + 1;
                    $seq = $seqByYear[$year];

                    $newNumber = sprintf('%s/%s/%04d/%d', $prefix, $code, $seq, $year);
                    $oldNumber = $enrollment->admission_number;

                    $this->line("  {$oldNumber} → {$newNumber}");

                    if (!$dryRun) {
                        $enrollment->admission_number = $newNumber;
                        $enrollment->save();
                    }
                }
            }
        });

        $this->info($dryRun
            ? 'Dry run complete. Run without --dry-run to apply changes.'
            : '✅ Done! All admission numbers updated.');

        return self::SUCCESS;
    }
}
