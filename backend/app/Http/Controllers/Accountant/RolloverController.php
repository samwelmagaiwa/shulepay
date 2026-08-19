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
use Illuminate\Support\Facades\DB;

class RolloverController extends Controller
{
    /**
     * GET /rollover/preview?academic_year_id=&school_id=
     * Returns a preview of enrollments and proposed next classes without writing anything.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'school_id'        => ['required', 'integer', 'exists:schools,id'],
        ]);

        $school = School::findOrFail($data['school_id']);

        // Get all school classes sorted by sort_order
        $allClasses = SchoolClass::where('school_id', $school->id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $sortedClasses = $allClasses->values();

        // Build a map: class_id => next class_id (based on sort_order)
        $nextClassMap = [];
        foreach ($sortedClasses as $index => $class) {
            $nextIndex              = $index + 1;
            $nextClassMap[$class->id] = $sortedClasses->get($nextIndex)?->id ?? null;
        }

        // Get active enrollments in the given academic year
        $enrollments = Enrollment::with(['student', 'schoolClass'])
            ->where('school_id', $school->id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('status', 'active')
            ->get();

        $preview = $enrollments->map(function (Enrollment $enrollment) use ($nextClassMap, $allClasses) {
            $currentClass = $allClasses->get($enrollment->school_class_id);
            $nextClassId  = $nextClassMap[$enrollment->school_class_id] ?? null;
            $nextClass    = $nextClassId ? $allClasses->get($nextClassId) : null;

            return [
                'student_id'            => $enrollment->student_id,
                'student_name'          => $enrollment->student->fullName(),
                'admission_number'      => $enrollment->admission_number,
                'current_class_id'      => $enrollment->school_class_id,
                'current_class_name'    => $currentClass?->name,
                'proposed_class_id'     => $nextClassId,
                'proposed_class_name'   => $nextClass?->name ?? 'No next class (graduated)',
                'proposed_action'       => $nextClassId ? 'promoted' : 'graduated',
            ];
        });

        return response()->json([
            'academic_year_id' => $data['academic_year_id'],
            'school_id'        => $data['school_id'],
            'total'            => $preview->count(),
            'enrollments'      => $preview->values(),
        ]);
    }

    /**
     * POST /rollover/execute
     * Executes the rollover: creates new enrollments for new academic year.
     */
    public function execute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id'     => ['required', 'integer', 'exists:academic_years,id'],
            'new_academic_year_id' => ['required', 'integer', 'exists:academic_years,id', 'different:academic_year_id'],
            'school_id'            => ['required', 'integer', 'exists:schools,id'],
            'promotions'           => ['required', 'array', 'min:1'],
            'promotions.*.student_id'       => ['required', 'integer', 'exists:students,id'],
            'promotions.*.new_school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'promotions.*.action'            => ['required', 'string', 'in:promoted,repeated,graduated,dropped'],
        ]);

        $school         = School::findOrFail($data['school_id']);
        $newAcademicYear = AcademicYear::findOrFail($data['new_academic_year_id']);

        $summary = DB::transaction(function () use ($data, $school, $newAcademicYear) {
            $imported  = 0;
            $graduated = 0;
            $dropped   = 0;
            $errors    = [];

            foreach ($data['promotions'] as $promo) {
                $student = Student::find($promo['student_id']);

                if (! $student) {
                    $errors[] = "Student ID {$promo['student_id']} not found.";
                    continue;
                }

                // Check for duplicate enrollment in new year
                $exists = Enrollment::where('student_id', $student->id)
                    ->where('academic_year_id', $data['new_academic_year_id'])
                    ->where('school_id', $school->id)
                    ->exists();

                if ($exists) {
                    $errors[] = "Student {$student->fullName()} already enrolled in new academic year.";
                    continue;
                }

                $action = $promo['action'];

                if ($action === 'graduated' || $action === 'dropped') {
                    // Mark student status accordingly
                    $student->update(['status' => $action === 'graduated' ? 'graduated' : 'inactive']);

                    // Deactivate current enrollment
                    Enrollment::where('student_id', $student->id)
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->where('school_id', $school->id)
                        ->update(['status' => $action === 'graduated' ? 'graduated' : 'dropped']);

                    $action === 'graduated' ? $graduated++ : $dropped++;
                    continue;
                }

                // promoted or repeated: create new enrollment
                // For 'repeated', new_school_class_id should be the same class
                $newClassId = (int) $promo['new_school_class_id'];

                // Generate new admission number
                $admissionNumber = $school->nextAdmissionNumber();

                Enrollment::create([
                    'student_id'      => $student->id,
                    'school_id'       => $school->id,
                    'school_class_id' => $newClassId,
                    'academic_year_id'=> $data['new_academic_year_id'],
                    'admission_number'=> $admissionNumber,
                    'status'          => 'active',
                    'admitted_at'     => $newAcademicYear->start_date ?? now(),
                ]);

                // Deactivate old enrollment
                Enrollment::where('student_id', $student->id)
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('school_id', $school->id)
                    ->update(['status' => 'completed']);

                $imported++;
            }

            AuditLogger::log('rollover_executed', null, [
                'after' => [
                    'from_academic_year_id' => $data['academic_year_id'],
                    'to_academic_year_id'   => $data['new_academic_year_id'],
                    'school_id'             => $data['school_id'],
                    'promoted'              => $imported,
                    'graduated'             => $graduated,
                    'dropped'               => $dropped,
                    'errors_count'          => count($errors),
                ],
            ]);

            return [
                'promoted'  => $imported,
                'graduated' => $graduated,
                'dropped'   => $dropped,
                'errors'    => $errors,
            ];
        });

        return response()->json([
            'message' => 'Rollover executed successfully.',
            'summary' => $summary,
        ]);
    }
}
