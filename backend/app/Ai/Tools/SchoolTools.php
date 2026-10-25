<?php

namespace App\Ai\Tools;

class SchoolTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_school_overview',
                'description' => 'Get the school name, number of active students, number of classes, and the current academic year and term with its dates. Use for general "tell me about the school" questions.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_class_list',
                'description' => 'List every class with its capacity, how many active students are enrolled, and the occupancy percentage. Use for questions about class sizes, capacity, or how full classes are.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Count enrolments of this academic year only (optional).'],
                    ],
                ],
            ],
            [
                'name' => 'get_current_term',
                'description' => 'Get the current term: name, number, academic year, start and end dates and days remaining. Use for "which term are we in", "when does the term end".',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'get_subject_list',
                'description' => 'List the active subjects with their codes.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }
}
