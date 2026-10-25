<?php

namespace App\Services\Exam;

use App\Imports\SupplierSheet;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Marks in and out of a spreadsheet: a template that lists the class (with any marks already entered)
 * and an import that validates every row first and saves nothing unless the whole file is clean.
 */
class MarksImportService
{
    public const HEADERS = ['admission_number', 'student_name', 'marks', 'absent'];

    public function __construct(
        private readonly MarksService $marks,
        private readonly ExamResultsService $results,
    ) {}

    public function template(Exam $exam, SchoolClass $class, Subject $subject, ?User $actor): string
    {
        $grid = $this->marks->grid($exam, $class, $subject, $actor);
        $lines = [implode(',', self::HEADERS)];
        foreach ($grid['students'] as $student) {
            $lines[] = implode(',', [
                $this->cell($student['admission_number']),
                $this->cell($student['full_name']),
                $student['marks'] ?? '',
                $student['absent'] ? 'yes' : '',
            ]);
        }

        // A byte-order mark so Excel opens the Swahili names correctly.
        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return array{saved: int, cleared: int}
     *
     * @throws ExamException with the list of problem rows when anything is wrong; nothing is saved then
     */
    public function import(Exam $exam, SchoolClass $class, Subject $subject, UploadedFile $file, ?User $actor): array
    {
        $sheet = Excel::toArray(new SupplierSheet, $file)[0] ?? [];
        if ($sheet === [] || ! array_key_exists('admission_number', $sheet[0]) || ! array_key_exists('marks', $sheet[0])) {
            throw ExamException::invalid(__('exams.err.import_header'), 'IMPORT_HEADER');
        }

        $enrolled = $this->results->enrolled($class)->keyBy(fn ($e) => mb_strtolower(trim((string) $e->admission_number)));
        $max = (int) $exam->out_of;
        $entries = [];
        $problems = [];

        foreach ($sheet as $i => $row) {
            $line = $i + 2;
            $adm = mb_strtolower(trim((string) ($row['admission_number'] ?? '')));
            $marks = trim((string) ($row['marks'] ?? ''));
            $absent = in_array(mb_strtolower(trim((string) ($row['absent'] ?? ''))), ['yes', 'y', 'ndiyo', '1', 'true', 'x'], true);

            if ($adm === '' && $marks === '' && ! $absent) {
                continue;
            }
            $enrollment = $enrolled->get($adm);
            if (! $enrollment) {
                $problems[] = ['line' => $line, 'problem' => 'student', 'value' => $row['admission_number'] ?? ''];

                continue;
            }
            if ($marks === '' && ! $absent) {
                continue; // nothing entered for this pupil; leave any stored mark alone
            }
            if (! $absent) {
                $marks = str_replace(',', '.', $marks);
                if (! is_numeric($marks) || (float) $marks < 0 || (float) $marks > $max) {
                    $problems[] = ['line' => $line, 'problem' => 'marks', 'value' => $row['marks'], 'max' => $max];

                    continue;
                }
            }
            $entries[] = ['student_id' => $enrollment->student_id, 'marks' => $absent ? null : (float) $marks, 'absent' => $absent];
        }

        if ($problems) {
            throw ExamException::invalid(__('exams.err.import_problems', ['count' => count($problems)]), 'IMPORT_PROBLEMS', ['problems' => $problems]);
        }
        if (! $entries) {
            throw ExamException::invalid(__('exams.err.import_none'), 'IMPORT_EMPTY');
        }

        return $this->marks->save($exam, $class, $subject, $entries, $actor);
    }

    private function cell(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/[",\r\n]/', $value) ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
