<?php

namespace App\Ai\Tools;

class ExamTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_exam_list',
                'description' => 'List exams that have been created: id, name, classes, status (draft = being set up, open = teachers are entering marks, published = results are visible to parents), total marks, marks deadline and dates. Use first to find an exam id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['draft', 'open', 'published', 'all'], 'description' => 'Filter by exam status. Default: all.'],
                        'class_name' => ['type' => 'string', 'description' => 'Filter by class name (optional).'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 10.'],
                    ],
                ],
            ],
            [
                'name' => 'get_exam_results_summary',
                'description' => 'Get a summary of results for an exam, class by class: average (percent), pass rate, grade distribution and divisions. Marks are percentages of the exam total. Use when asked how students performed in an exam.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID to get results for (required).'],
                        'class_name' => ['type' => 'string', 'description' => 'Filter by class name (optional).'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
            [
                'name' => 'get_top_students',
                'description' => 'List top-performing students in an exam by average percent, with class, position in class, grade and division. Use when asked about best students, top scorers, or exam ranking.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID (required).'],
                        'class_name' => ['type' => 'string', 'description' => 'Only this class (optional).'],
                        'limit' => ['type' => 'integer', 'description' => 'How many top students to return. Default: 10.'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
            [
                'name' => 'get_exam_progress',
                'description' => 'Show how far teachers are with entering marks for an exam: marks entered out of expected, the deadline, and which class and subject is still waiting, with the teacher responsible. Use when asked who has not entered marks, what is delaying results, or whether an exam is ready to publish.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID (required). Use get_exam_list to find it.'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
            [
                'name' => 'get_exam_analysis',
                'description' => 'Analyse one class in an exam: class average, pass rate, grade spread, strongest and weakest subjects, top pupils, pupils needing support, and how the class average moved across the exams of the year. Use for deeper questions about performance or what to improve.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID (required).'],
                        'class_name' => ['type' => 'string', 'description' => 'Class name (optional; the first class of the exam is used when left out).'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
            [
                'name' => 'get_exam_comments_status',
                'description' => 'Show where the report-card comments stand for an exam: per class, whether the class teacher has submitted and whether the head has approved, and how many comments are missing. Use when asked why results cannot be published yet or who must approve comments.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID (required).'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
            [
                'name' => 'get_student_exam_history',
                'description' => 'Show one pupil\'s results across the exams of their class: average percent, grade, position and division in each, so progress can be seen. Use when asked how a pupil is doing or improving.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'student_name' => ['type' => 'string', 'description' => 'Student name to search for (required).'],
                    ],
                    'required' => ['student_name'],
                ],
            ],
            [
                'name' => 'get_subject_averages',
                'description' => 'Get average scores (percent) per subject for an exam, best first. Use when asked which subject students did best or worst in, or subject performance comparison.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'exam_id' => ['type' => 'integer', 'description' => 'The exam ID (required).'],
                        'class_name' => ['type' => 'string', 'description' => 'Filter by class name (optional).'],
                    ],
                    'required' => ['exam_id'],
                ],
            ],
        ];
    }
}
