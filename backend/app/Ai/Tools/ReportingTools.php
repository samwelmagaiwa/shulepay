<?php

namespace App\Ai\Tools;

class ReportingTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_revenue_trend',
                'description' => 'Show monthly fee collection totals for the last N months. Use when asked about revenue trends, monthly income, collection performance over time, or how collections compare month to month.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'months' => ['type' => 'integer', 'description' => 'How many months back to show. Default: 6.'],
                    ],
                ],
            ],
            [
                'name' => 'get_budget_summary',
                'description' => 'Show live budget vs actual spending for the current or a specific academic year. Returns planned vs actual per category, utilisation %, days remaining in the year, daily burn rate, and projected full-year spend. Use when asked about the budget, planned vs actual expenses, budget performance, how much is left, or which categories are over budget.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Academic year ID (optional, defaults to current year).'],
                    ],
                ],
            ],
            [
                'name' => 'get_budget_forecast',
                'description' => 'Predict next year\'s budget by analysing all previous years\' actual spending per category. Computes year-over-year growth rates and recommends a budget amount for each category with a confidence level. Use when asked to predict, forecast, recommend, or plan the next budget.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'get_budget_history',
                'description' => 'Show a year-by-year comparison of all past budgets: planned vs actual, utilisation, and overall spending trend (growing or shrinking). Use when asked about budget history, past budgets, trends over years, or how this year compares to previous years.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'get_discount_summary',
                'description' => 'Show discounts applied to student fees: total amount discounted, count of students with discounts. Use when asked about waivers, concessions, discounts, or fee reductions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Filter by academic year (optional).'],
                    ],
                ],
            ],
            [
                'name' => 'get_refund_summary',
                'description' => 'Show refunds issued to students: total amount refunded, count of refunds. Use when asked about refunds, money returned, or overpayments.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD (optional).'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD (optional).'],
                    ],
                ],
            ],
            [
                'name' => 'get_student_financial_summary',
                'description' => 'Get financial summary for a specific student: total invoiced, total paid, outstanding balance, and list of invoices. Use when asked how much a student owes, their payment history, or fee status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'student_name' => ['type' => 'string', 'description' => 'Student full name or partial name to search (required).'],
                    ],
                    'required' => ['student_name'],
                ],
            ],
            [
                'name' => 'get_guardian_list',
                'description' => 'List guardians/parents of students with their contact information. Use when asked about parents, guardians, or emergency contacts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'student_name' => ['type' => 'string', 'description' => 'Filter by student name (optional).'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 15.'],
                    ],
                ],
            ],
        ];
    }
}
