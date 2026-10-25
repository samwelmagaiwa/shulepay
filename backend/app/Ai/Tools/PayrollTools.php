<?php

namespace App\Ai\Tools;

class PayrollTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_payroll_summary',
                'description' => 'Payroll totals for one month: employees on payroll, basic salary, allowances, deductions, gross and net pay, and how many are paid or still pending.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => ['type' => 'string', 'description' => 'Month as YYYY-MM. Default: current month.'],
                    ],
                ],
            ],
            [
                'name' => 'get_employee_list',
                'description' => 'List employees with staff number, role, department and status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'department' => ['type' => 'string', 'description' => 'Filter by department (optional, partial).'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 20.'],
                    ],
                ],
            ],
        ];
    }
}
