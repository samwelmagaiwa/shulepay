<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportExcelStudents extends Command
{
    protected $signature = 'import:students-excel
                                {file : Full path to the Excel file}
                                {--school-id=1 : School ID to import into}
                                {--year=2026 : Academic year name}
                                {--dry-run : Parse and validate only, do not write to database}';

    protected $description = 'Import students from a school fees Excel file. Each sheet = one class. Column A = full name, Column B = total fee (TZS), Columns C-F = payments already made.';

    /** Sheet name → class name mapping */
    private array $classMap = [
        'pp1' => 'PP 1',
        'pp2' => 'PP 2',
        'std 1' => 'Darasa la 1',
        'std 2' => 'Darasa la 2',
        'std 3' => 'Darasa la 3',
        'std 4' => 'Darasa la 4',
        'std 5' => 'Darasa la 5',
        'std 6' => 'Darasa la 6',
        'std 7' => 'Darasa la 7',
    ];

    private int $imported = 0;

    private int $skipped = 0;

    private array $errors = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $schoolId = (int) $this->option('school-id');
        $yearName = $this->option('year');
        $dryRun = $this->option('dry-run');

        // ── Validate file ────────────────────────────────────────────────────
        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return 1;
        }

        $school = School::find($schoolId);
        if (! $school) {
            $this->error("School ID {$schoolId} not found.");

            return 1;
        }

        $this->info("School : {$school->name} ({$school->code})");
        $this->info("Year   : {$yearName}");
        $this->info("File   : {$filePath}");
        $this->info('Mode   : '.($dryRun ? 'DRY RUN (no writes)' : 'LIVE IMPORT'));
        $this->newLine();

        // ── Load spreadsheet ─────────────────────────────────────────────────
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            $this->error('Cannot read Excel: '.$e->getMessage());

            return 1;
        }

        // ── Ensure academic year + term exist ────────────────────────────────
        [$academicYear, $term] = $this->ensureYearAndTerm($yearName, $schoolId, $dryRun);

        // ── Process each sheet ───────────────────────────────────────────────
        $sheetNames = $spreadsheet->getSheetNames();
        $bar = $this->output->createProgressBar(0);

        foreach ($sheetNames as $sheetName) {
            $key = strtolower(trim($sheetName));
            if (! array_key_exists($key, $this->classMap)) {
                $this->line("  Skipping sheet '{$sheetName}' (not in class map)");

                continue;
            }

            $className = $this->classMap[$key];
            $schoolClass = $this->ensureClass($className, $schoolId, $dryRun);
            $sheet = $spreadsheet->getSheetByName($sheetName);
            $rows = $this->parseSheet($sheet);

            $this->info("Sheet '{$sheetName}' → {$className}: {$rows->count()} students");
            $bar->setMaxSteps($bar->getMaxSteps() + $rows->count());

            foreach ($rows as $row) {
                try {
                    $this->importRow($row, $school, $schoolClass, $academicYear, $term, $dryRun);
                } catch (\Exception $e) {
                    $this->errors[] = "  [{$sheetName}] {$row['full_name']}: ".$e->getMessage();
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──────────────────────────────────────────────────────────
        $this->table(['Metric', 'Count'], [
            ['Imported', $this->imported],
            ['Skipped (duplicate)', $this->skipped],
            ['Errors', count($this->errors)],
        ]);

        if ($this->errors) {
            $this->warn('Errors:');
            foreach ($this->errors as $err) {
                $this->line($err);
            }
        }

        if ($dryRun) {
            $this->info('[DRY RUN] No data was written to the database.');
        } else {
            $this->info('Import complete.');
        }

        return 0;
    }

    // ── Parse all rows from a sheet ──────────────────────────────────────────
    private function parseSheet(Worksheet $sheet): Collection
    {
        $rows = collect();
        $highest = $sheet->getHighestRow();

        for ($rowNum = 1; $rowNum <= $highest; $rowNum++) {
            $nameRaw = trim((string) $sheet->getCell("A{$rowNum}")->getValue());

            // Skip empty rows, header-like rows, totals
            if (empty($nameRaw)) {
                continue;
            }
            if (is_numeric($nameRaw)) {
                continue;
            }
            if (str_starts_with(strtolower($nameRaw), 'total')) {
                continue;
            }
            if (str_starts_with(strtolower($nameRaw), 'name')) {
                continue;
            }

            // Parse fee column B — skip formula strings, non-numeric
            $rawFee = $sheet->getCell("B{$rowNum}")->getCalculatedValue();
            $totalFeeTZS = is_numeric($rawFee) ? (int) $rawFee : 0;

            // Parse payment columns C–F (already-paid installments)
            $paidTZS = 0;
            foreach (['C', 'D', 'E', 'F'] as $col) {
                $val = $sheet->getCell("{$col}{$rowNum}")->getCalculatedValue();
                if (is_numeric($val)) {
                    $paidTZS += (int) $val;
                }
                // Ignore text like "SHIFT", "paid 2025"
            }

            [$firstName, $middleName, $lastName] = $this->parseName($nameRaw);

            $rows->push([
                'full_name' => $nameRaw,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'total_fee_tzs' => $totalFeeTZS,
                'paid_tzs' => $paidTZS,
                'balance_tzs' => max(0, $totalFeeTZS - $paidTZS),
            ]);
        }

        return $rows;
    }

    // ── Parse "FIRST MIDDLE LAST" into three parts ───────────────────────────
    private function parseName(string $raw): array
    {
        // Normalise whitespace, title-case
        $parts = array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', $raw))));

        $firstName = Str::title($parts[0] ?? '');
        $middleName = count($parts) >= 3 ? Str::title($parts[1]) : '';
        $lastName = Str::title(end($parts));

        // If only two words: first + last, no middle
        if (count($parts) === 2) {
            $middleName = '';
        }

        return [$firstName, $middleName, $lastName];
    }

    // ── Ensure academic year exists (create if not) ──────────────────────────
    private function ensureYearAndTerm(string $yearName, int $schoolId, bool $dryRun): array
    {
        if ($dryRun) {
            $year = AcademicYear::where('name', $yearName)->first()
                 ?? new AcademicYear(['id' => 0, 'name' => $yearName]);
            $term = Term::whereHas('academicYear', fn ($q) => $q->where('name', $yearName))
                ->first()
                 ?? Term::first();

            return [$year, $term];
        }

        $year = AcademicYear::firstOrCreate(
            ['name' => $yearName],
            ['school_id' => $schoolId, 'start_date' => "{$yearName}-01-01", 'end_date' => "{$yearName}-12-31", 'is_current' => false]
        );

        // Use or create "Mwaka Mzima" term for full-year fee imports
        $term = Term::firstOrCreate(
            ['name' => 'Mwaka Mzima', 'academic_year_id' => $year->id],
            ['number' => 1, 'start_date' => "{$yearName}-01-01", 'end_date' => "{$yearName}-12-31", 'is_current' => false]
        );

        return [$year, $term];
    }

    // ── Ensure school class exists (create PP1/PP2 if missing) ───────────────
    private function ensureClass(string $className, int $schoolId, bool $dryRun): SchoolClass
    {
        $class = SchoolClass::where('name', $className)->where('school_id', $schoolId)->first();
        if ($class) {
            return $class;
        }

        if ($dryRun) {
            return new SchoolClass(['id' => 0, 'name' => $className, 'school_id' => $schoolId]);
        }

        $this->info("  Creating class: {$className}");

        return SchoolClass::create([
            'name' => $className,
            'school_id' => $schoolId,
            'level' => str_starts_with($className, 'PP') ? 'primary' : 'primary',
        ]);
    }

    // ── Import a single student row ──────────────────────────────────────────
    private function importRow(array $row, School $school, SchoolClass $class, AcademicYear $year, Term $term, bool $dryRun): void
    {
        if ($dryRun) {
            $this->imported++;

            return;
        }

        DB::transaction(function () use ($row, $school, $class, $year, $term) {

            // ── Duplicate check (same name + class + year) ───────────────────
            $exists = Student::where('first_name', $row['first_name'])
                ->where('last_name', $row['last_name'])
                ->whereHas('enrollments', fn ($q) => $q->where('school_class_id', $class->id)
                    ->where('academic_year_id', $year->id))
                ->exists();

            if ($exists) {
                $this->skipped++;

                return;
            }

            // ── Auto-generate admission number (PRM/CODE/0001/YEAR format) ──
            $admissionNum = $school->nextAdmissionNumber();

            // ── Create student ───────────────────────────────────────────────
            $student = Student::create([
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'nationality' => 'Tanzanian',
                'status' => 'active',
                'gender' => 'male',
            ]);

            // ── Create enrollment ────────────────────────────────────────────
            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'school_id' => $school->id,
                'school_class_id' => $class->id,
                'academic_year_id' => $year->id,
                'admission_number' => $admissionNum,
                'admitted_at' => now()->toDateString(),
                'status' => 'active',
            ]);

            // ── Create invoice if fee > 0 ────────────────────────────────────
            if ($row['total_fee_tzs'] > 0) {
                $totalCents = $row['total_fee_tzs'] * 100;

                $invoice = Invoice::create([
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'academic_year_id' => $year->id,
                    'term_id' => $term->id,
                    'invoice_number' => 'INV-'.str_replace('/', '-', $admissionNum),
                    'total_amount_cents' => $totalCents,
                    'arrears_cents' => 0,
                    'discount_cents' => 0,
                    'status' => 'unpaid',
                    'generated_at' => now(),
                    'generated_by' => 1,
                    'due_date' => now()->endOfYear()->toDateString(),
                ]);

                // Create one invoice line: school fees
                $invoice->lines()->create([
                    'description' => "Ada ya Shule — {$class->name} ({$year->name})",
                    'amount_cents' => $totalCents,
                ]);

                // ── Record existing payments (columns C–F) ───────────────────
                if ($row['paid_tzs'] > 0) {
                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'student_id' => $student->id,
                        'school_id' => $school->id,
                        'amount_cents' => $row['paid_tzs'] * 100,
                        'method' => 'cash',
                        'paid_at' => now()->toDateString(),
                        'reference_number' => 'IMPORTED-2026',
                        'recorded_by' => 1,
                    ]);
                }

                // Sync invoice status (unpaid / partial / paid)
                $invoice->syncStatus();
            }

            // ── Audit log ────────────────────────────────────────────────────
            AuditLogger::log('student.imported_excel', $student, [
                'class' => $class->name,
                'year' => $year->name,
                'fee_tzs' => $row['total_fee_tzs'],
                'paid_tzs' => $row['paid_tzs'],
            ]);

            $this->imported++;
        });
    }
}
