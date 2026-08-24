<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use Illuminate\Console\Command;

class FixOrphanedActiveEnrollments extends Command
{
    protected $signature = 'enrollments:fix-orphaned-active {--force : Actually update. Without this flag, only reports what would change.}';

    protected $description = "Find enrollments still marked 'active' whose student was soft-deleted before StudentController::destroy() started marking enrollments 'dropped' on deletion. Corrects the status field only — no rows are deleted.";

    public function handle(): int
    {
        $force = $this->option('force');

        $orphaned = Enrollment::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereDoesntHave('student') // student relation excludes soft-deleted students by default
            ->with(['school:id,name'])
            ->get();

        if ($orphaned->isEmpty()) {
            $this->info("No orphaned 'active' enrollments found. Nothing to do.");

            return self::SUCCESS;
        }

        $this->warn("Found {$orphaned->count()} enrollment(s) still 'active' for a deleted student:");
        $this->table(
            ['Enrollment ID', 'Student ID', 'School', 'Admission No.'],
            $orphaned->map(fn ($e) => [
                $e->id,
                $e->student_id,
                $e->school?->name ?? "#{$e->school_id}",
                $e->admission_number,
            ])
        );

        if (! $force) {
            $this->line('');
            $this->line("DRY RUN — nothing changed. Re-run with --force to set these enrollments'");
            $this->line("status to 'dropped'. This does not delete any row.");

            return self::SUCCESS;
        }

        $this->line('');
        $ids = $orphaned->pluck('id');
        Enrollment::withoutGlobalScope('school')->whereIn('id', $ids)->update(['status' => 'dropped']);
        $this->info("Updated {$ids->count()} enrollment(s) to status 'dropped'.");

        return self::SUCCESS;
    }
}
