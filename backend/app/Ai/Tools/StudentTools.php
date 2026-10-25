<?php

namespace App\Ai\Tools;

class StudentTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_student_stats',
                'description' => 'Get total student counts: total enrolled, active students, by gender, by class level. Use when asked about how many students are in the school, enrollment numbers, student population.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Filter by academic year (optional, defaults to current year).'],
                    ],
                ],
            ],
            [
                'name' => 'get_class_distribution',
                'description' => 'Get number of students in each class. Use when asked about class sizes, how many students are in each class, class enrollment breakdown.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Academic year ID (optional, defaults to current).'],
                    ],
                ],
            ],
            [
                'name' => 'get_student_list',
                'description' => 'List students with their full details: name, admission number, class, gender, status, date of birth, phone. Use when asked to list students, find a specific student, search by name, or show admission numbers.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'class_name' => ['type' => 'string', 'description' => 'Filter by class name (optional).'],
                        'search' => ['type' => 'string', 'description' => 'Search by student name (optional).'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'transferred', 'graduated', 'dropped'], 'description' => 'Filter by student status. Default: active.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 15.'],
                    ],
                ],
            ],
            [
                'name' => 'get_new_enrollments',
                'description' => 'Get count or list of newly enrolled students for a date range. Use when asked about new admissions, new students this term/month/year.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD.'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 20.'],
                    ],
                ],
            ],
            [
                'name' => 'get_academic_years',
                'description' => 'List all academic years and their terms. Use when the user asks about the current year, terms, or when they need to understand the academic calendar.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }
}
