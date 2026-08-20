<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class BulkImportController extends Controller
{
    /** CSV column headers for the download template. */
    private const HEADERS = [
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'school_code',
        'class_name',
        'academic_year_name',
        'admission_date',
    ];

    /**
     * GET /bulk-import/template
     * Returns a CSV file with the expected column headers.
     */
    public function template(Request $request): Response
    {
        $sample = implode(',', self::HEADERS)."\r\n"
            ."Juma,Mwangi,2010-03-15,male,MSG,Darasa 1,2024/2025,2024-01-15\r\n"
            ."Amina,Hassan,2011-07-22,female,MSG,Darasa 2,2024/2025,2024-01-15\r\n";

        return response($sample, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bulk-import-template.csv"',
        ]);
    }

    /**
     * POST /bulk-import/preview
     * Accepts a CSV or XLSX file, parses and validates rows, returns results without writing.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $file = $request->file('file');

        try {
            $rows = $this->parseFile($file);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Faili haiwezi kusomwa: '.$e->getMessage(),
            ], 422);
        }

        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // 1-based, +1 for header row
            $rowData = $this->normalizeRow($row);
            $errs = $this->validateRow($rowData, $line);

            if (count($errs) > 0) {
                $errors[] = ['line' => $line, 'row' => $rowData, 'errors' => $errs];
            } else {
                $valid[] = $rowData;
            }
        }

        return response()->json([
            'total' => count($rows),
            'valid' => $valid,
            'errors' => $errors,
        ]);
    }

    /**
     * POST /bulk-import/import
     * Accepts pre-validated rows as JSON array and persists them.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.first_name' => ['required', 'string', 'max:100'],
            'rows.*.last_name' => ['required', 'string', 'max:100'],
            'rows.*.date_of_birth' => ['required', 'date'],
            'rows.*.gender' => ['required', 'string', 'in:male,female'],
            'rows.*.school_code' => ['required', 'string'],
            'rows.*.class_name' => ['required', 'string'],
            'rows.*.academic_year_name' => ['required', 'string'],
            'rows.*.admission_date' => ['nullable', 'date'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($data['rows'] as $index => $row) {
                $school = School::where('code', $row['school_code'])->first();
                if (! $school) {
                    $errors[] = "Row {$index}: School code '{$row['school_code']}' not found.";

                    continue;
                }

                $class = SchoolClass::where('school_id', $school->id)
                    ->where('name', $row['class_name'])
                    ->first();
                if (! $class) {
                    $errors[] = "Row {$index}: Class '{$row['class_name']}' not found in school '{$school->name}'.";

                    continue;
                }

                $academicYear = AcademicYear::where('school_id', $school->id)
                    ->where('name', $row['academic_year_name'])
                    ->first();
                if (! $academicYear) {
                    $errors[] = "Row {$index}: Academic year '{$row['academic_year_name']}' not found for school '{$school->name}'.";

                    continue;
                }

                // Duplicate check by first_name + last_name + dob
                $duplicate = Student::where('first_name', $row['first_name'])
                    ->where('last_name', $row['last_name'])
                    ->whereDate('date_of_birth', $row['date_of_birth'])
                    ->first();

                if ($duplicate) {
                    // Check if already enrolled in this year/school
                    $alreadyEnrolled = Enrollment::where('student_id', $duplicate->id)
                        ->where('academic_year_id', $academicYear->id)
                        ->where('school_id', $school->id)
                        ->exists();

                    if ($alreadyEnrolled) {
                        $skipped++;

                        continue;
                    }

                    // Enroll existing student
                    $student = $duplicate;
                } else {
                    $student = Student::create([
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'],
                        'gender' => $row['gender'],
                        'status' => 'active',
                    ]);
                }

                $admissionNumber = $school->nextAdmissionNumber();

                Enrollment::create([
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'school_class_id' => $class->id,
                    'academic_year_id' => $academicYear->id,
                    'admission_number' => $admissionNumber,
                    'status' => 'active',
                    'admitted_at' => $row['admission_date'] ?? now(),
                ]);

                $imported++;
            }

            AuditLogger::log('bulk_import_executed', null, [
                'after' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => count($errors),
                ],
            ]);

            return compact('imported', 'skipped', 'errors');
        });

        return response()->json([
            'message' => 'Import complete.',
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ]);
    }

    /**
     * Parse an uploaded file (CSV or XLSX) and return array of associative rows.
     */
    private function parseFile(UploadedFile $file): array
    {
        $mime = $file->getMimeType();

        $xlsxMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];

        if (in_array($mime, $xlsxMimes)) {
            return $this->parseXlsx($file->getRealPath());
        }

        return $this->parseCsv($file->getRealPath());
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $headers = null;

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $line);

                continue;
            }

            if (count($line) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, array_map('trim', $line));
        }

        fclose($handle);

        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $reader = new Xlsx;
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (empty($data)) {
            return [];
        }

        $headers = array_map('trim', $data[0]);
        $rows = [];

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $rows[] = array_combine($headers, array_map(fn ($v) => trim((string) ($v ?? '')), $row));
        }

        return $rows;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'first_name' => trim($row['first_name'] ?? ''),
            'last_name' => trim($row['last_name'] ?? ''),
            'date_of_birth' => trim($row['date_of_birth'] ?? ''),
            'gender' => strtolower(trim($row['gender'] ?? '')),
            'school_code' => strtoupper(trim($row['school_code'] ?? '')),
            'class_name' => trim($row['class_name'] ?? ''),
            'academic_year_name' => trim($row['academic_year_name'] ?? ''),
            'admission_date' => trim($row['admission_date'] ?? '') ?: null,
        ];
    }

    private function validateRow(array $row, int $line): array
    {
        $errs = [];

        if (empty($row['first_name'])) {
            $errs[] = 'first_name is required.';
        }
        if (empty($row['last_name'])) {
            $errs[] = 'last_name is required.';
        }
        if (empty($row['date_of_birth']) || ! strtotime($row['date_of_birth'])) {
            $errs[] = 'date_of_birth is invalid or missing (expected YYYY-MM-DD).';
        }
        if (! in_array($row['gender'], ['male', 'female'])) {
            $errs[] = "gender must be 'male' or 'female'.";
        }
        if (empty($row['school_code'])) {
            $errs[] = 'school_code is required.';
        } elseif (! School::where('code', $row['school_code'])->exists()) {
            $errs[] = "school_code '{$row['school_code']}' not found.";
        }
        if (empty($row['class_name'])) {
            $errs[] = 'class_name is required.';
        }
        if (empty($row['academic_year_name'])) {
            $errs[] = 'academic_year_name is required.';
        }

        return $errs;
    }
}
