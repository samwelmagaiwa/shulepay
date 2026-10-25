<?php

namespace App\Ai\Tools;

class AttendanceTools
{
    public static function definitions(): array
    {
        $range = [
            'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD. Default: first day of this month.'],
            'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD. Default: today.'],
            'class_name' => ['type' => 'string', 'description' => 'Only this class (optional, partial name).'],
        ];

        return [
            [
                'name' => 'get_attendance_summary',
                'description' => 'Attendance for a period: records, present, absent, late, attendance rate (late counts as attending) and a per-class breakdown. Use for "how was attendance this week/month".',
                'parameters' => ['type' => 'object', 'properties' => $range],
            ],
            [
                'name' => 'get_student_attendance',
                'description' => 'Attendance record of one student: days recorded, present, absent, late, rate and recent absences.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'student_name' => ['type' => 'string', 'description' => 'Full or partial name of the student.'],
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD (optional).'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD (optional).'],
                    ],
                    'required' => ['student_name'],
                ],
            ],
            [
                'name' => 'get_attendance_absentees',
                'description' => 'Who was absent or late on one day, with class and remarks.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'description' => 'Date YYYY-MM-DD. Default: today.'],
                        'class_name' => ['type' => 'string', 'description' => 'Only this class (optional).'],
                    ],
                ],
            ],
            [
                'name' => 'get_low_attendance_students',
                'description' => 'Pupils whose attendance rate in a period is below a threshold, weakest first.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => $range + [
                        'below_percent' => ['type' => 'integer', 'description' => 'Threshold percent. Default 80.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max pupils, default 15.'],
                    ],
                ],
            ],
        ];
    }
}
