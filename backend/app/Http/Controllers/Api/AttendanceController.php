<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\SchoolNotification;
use App\Models\Student;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller {

    /**
     * Get the attendance register for a class on a given date.
     * Returns all active enrolled students with their attendance status if already marked.
     */
    public function getRegister(Request $request) {
        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'date'            => 'nullable|date',
        ]);

        $classId  = $request->school_class_id;
        $date     = $request->date ?? now()->toDateString();

        $class    = SchoolClass::findOrFail($classId);
        $schoolId = $class->school_id;

        // Get active enrollments for this class
        $enrollments = Enrollment::where('school_class_id', $classId)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->with('student')
            ->get();

        // Get existing attendance records for this date
        $existing = Attendance::where('school_class_id', $classId)
            ->where('date', $date)
            ->get()
            ->keyBy('student_id');

        $students = $enrollments->map(function ($enrollment) use ($existing) {
            $student    = $enrollment->student;
            $attendance = $existing->get($student->id);

            return [
                'student_id'       => $student->id,
                'full_name'        => $student->fullName(),
                'admission_number' => $enrollment->admission_number,
                'status'           => $attendance?->status ?? null,
                'remarks'          => $attendance?->remarks ?? null,
                'attendance_id'    => $attendance?->id ?? null,
            ];
        })->sortBy('full_name')->values();

        return response()->json([
            'class'    => ['id' => $class->id, 'name' => $class->name],
            'date'     => $date,
            'students' => $students,
        ]);
    }

    /**
     * Bulk mark/update attendance for a class on a date.
     */
    public function bulkMark(Request $request) {
        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'date'            => 'required|date',
            'records'         => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.status'     => 'required|in:present,absent,late',
            'records.*.remarks'    => 'nullable|string',
        ]);

        $classId  = $request->school_class_id;
        $date     = $request->date;
        $records  = $request->records;
        $schoolId = SchoolClass::findOrFail($classId)->school_id;
        $saved    = [];

        DB::transaction(function () use ($records, $classId, $schoolId, $date, &$saved) {
            foreach ($records as $rec) {
                $att = Attendance::updateOrCreate(
                    ['date' => $date, 'student_id' => $rec['student_id'], 'school_class_id' => $classId],
                    [
                        'school_id' => $schoolId,
                        'status'    => $rec['status'],
                        'remarks'   => $rec['remarks'] ?? null,
                    ]
                );
                $saved[] = $att;
            }
        });

        // Notify owners about absent students (in-app + SMS)
        $absentRecords = collect($records)->where('status', 'absent');
        if ($absentRecords->count() > 0) {
            $class     = SchoolClass::find($classId);
            $className = $class?->name ?? 'Darasa';

            $absentStudents = $absentRecords->map(function ($r) {
                $student    = Student::with('currentEnrollment')->find($r['student_id']);
                return $student ? [
                    'name'             => $student->fullName(),
                    'admission_number' => $student->currentEnrollment?->admission_number,
                ] : null;
            })->filter()->values();

            $absentNames = $absentStudents->pluck('name')->join(', ');

            // In-app notification
            SchoolNotification::create([
                'school_id'      => $schoolId,
                'type'           => 'absence_alert',
                'title'          => "Wanafunzi {$absentRecords->count()} hawakuhudhuria - {$className}",
                'body'           => "Tarehe {$date}: {$absentNames}",
                'data'           => [
                    'class_id'     => $classId,
                    'date'         => $date,
                    'absent_count' => $absentRecords->count(),
                ],
                'recipient_role' => 'owner',
                'is_read'        => false,
            ]);

            // SMS to school owner(s) + fixed alert number
            $ownerPhones = User::where('school_id', $schoolId)
                ->whereHas('roles', fn($q) => $q->where('name', 'owner'))
                ->whereNotNull('phone')
                ->pluck('phone')
                ->push('0743519104')
                ->unique()
                ->values()
                ->toArray();

            try {
                $sms = app(SmsService::class);
                foreach ($absentStudents as $student) {
                    $message = SmsTemplates::absenceAlert(
                        $student['name'],
                        $className,
                        $date,
                        $student['admission_number']
                    );
                    $sms->sendBulk($ownerPhones, $message);
                }
            } catch (\Throwable $e) {
                Log::warning('[AttendanceController] absence SMS failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'saved'        => count($saved),
            'absent_count' => $absentRecords->count(),
            'late_count'   => collect($records)->where('status', 'late')->count(),
        ]);
    }

    /**
     * Attendance summary for a date range (per class).
     */
    public function summary(Request $request) {
        $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $schoolId = app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id;

        $summary = Attendance::where('school_id', $schoolId)
            ->whereBetween('date', [$request->from_date, $request->to_date])
            ->select('school_class_id', 'status', DB::raw('count(*) as count'))
            ->groupBy('school_class_id', 'status')
            ->with('schoolClass:id,name')
            ->get()
            ->groupBy('school_class_id')
            ->map(function ($group) {
                $byStatus = $group->keyBy('status');
                return [
                    'class_id'   => $group->first()->school_class_id,
                    'class_name' => $group->first()->schoolClass?->name,
                    'present'    => (int) ($byStatus->get('present')?->count ?? 0),
                    'absent'     => (int) ($byStatus->get('absent')?->count ?? 0),
                    'late'       => (int) ($byStatus->get('late')?->count ?? 0),
                ];
            })
            ->values();

        return response()->json($summary);
    }

    /**
     * Detailed attendance report for a specific student.
     */
    public function studentReport(Request $request) {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'from_date'  => 'required|date',
            'to_date'    => 'required|date|after_or_equal:from_date',
        ]);

        $records = Attendance::where('student_id', $request->student_id)
            ->whereBetween('date', [$request->from_date, $request->to_date])
            ->orderBy('date')
            ->get();

        $student = Student::findOrFail($request->student_id);

        return response()->json([
            'student'     => ['id' => $student->id, 'name' => $student->fullName()],
            'from_date'   => $request->from_date,
            'to_date'     => $request->to_date,
            'total_days'  => $records->count(),
            'present'     => $records->where('status', 'present')->count(),
            'absent'      => $records->where('status', 'absent')->count(),
            'late'        => $records->where('status', 'late')->count(),
            'records'     => $records,
        ]);
    }
}
