<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\School;
use Illuminate\Console\Command;

/**
 * Corrects a school's `code`, which is the middle segment of every admission
 * number it issues (e.g. SEC/MGRTH/0001/2026).
 *
 * Only schools.code is written. Admission numbers already issued are NOT rewritten:
 * they are printed on receipts, invoices and certificates, so changing them after
 * the fact would invalidate documents parents already hold.
 */
class ChangeSchoolCode extends Command
{
    protected $signature = 'schools:change-code
                            {school : Current school code, or the numeric school id}
                            {new-code : The corrected code, e.g. MGRTH}
                            {--dry-run : Show what would change without writing}';

    protected $description = "Change a school's code (affects admission numbers issued from now on)";

    public function handle(): int
    {
        $needle = (string) $this->argument('school');
        $newCode = strtoupper(trim((string) $this->argument('new-code')));

        if ($newCode === '' || ! preg_match('/^[A-Z0-9]{2,20}$/', $newCode)) {
            $this->error('New code must be 2-20 characters, letters and digits only.');

            return self::FAILURE;
        }

        $school = is_numeric($needle)
            ? School::find((int) $needle)
            : School::whereRaw('UPPER(code) = ?', [strtoupper($needle)])->first();

        if (! $school) {
            $this->error("No school found matching '{$needle}'.");
            $this->line('Known schools:');
            foreach (School::all() as $s) {
                $this->line("  [{$s->id}] {$s->code} — {$s->name} ({$s->level?->value})");
            }

            return self::FAILURE;
        }

        if (strtoupper($school->code) === $newCode) {
            $this->info("{$school->name} already uses code {$newCode}. Nothing to do.");

            return self::SUCCESS;
        }

        $clash = School::whereRaw('UPPER(code) = ?', [$newCode])->where('id', '!=', $school->id)->first();
        if ($clash) {
            $this->error("Code {$newCode} is already used by [{$clash->id}] {$clash->name}.");

            return self::FAILURE;
        }

        $existing = Enrollment::withoutGlobalScope('school')->where('school_id', $school->id)->count();
        $prefix = $school->level?->admissionPrefix() ?? 'PRM';

        $this->newLine();
        $this->line("School:            [{$school->id}] {$school->name}");
        $this->line("Level:             {$school->level?->value}");
        $this->line("Code:              {$school->code}  ->  {$newCode}");
        $this->line("Existing students: {$existing} (admission numbers left untouched)");
        $this->line('Next admission no: '.sprintf('%s/%s/%04d/%d', $prefix, $newCode, $this->peekNextSeq($school), now()->year));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No changes written.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm("Change {$school->code} to {$newCode}?", false)) {
            $this->info('Aborted. Nothing changed.');

            return self::SUCCESS;
        }

        $school->code = $newCode;
        $school->save();

        $this->info("Done. {$school->name} now uses code {$newCode}.");
        $this->line('Admission numbers already issued keep their original prefix.');

        return self::SUCCESS;
    }

    /** Next sequence for this school and year, independent of the code. */
    private function peekNextSeq(School $school): int
    {
        $year = now()->year;

        $max = Enrollment::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('admission_number', 'like', "%/{$year}")
            ->get(['admission_number'])
            ->map(fn ($e) => preg_match('#/(\d+)/'.$year.'$#', $e->admission_number, $m) ? (int) $m[1] : 0)
            ->max();

        return ((int) $max) + 1;
    }
}
