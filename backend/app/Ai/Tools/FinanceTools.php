<?php

namespace App\Ai\Tools;

class FinanceTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_payment_summary',
                'description' => 'Get total fee payments collected from students for a given period. Returns total amount, transaction count, and breakdown by payment method (cash, M-Pesa, bank transfer). Use this when asked about collections, revenue, payments received, or money collected.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format. Default: first day of current month.'],
                        'to_date' => ['type' => 'string', 'description' => 'End date in YYYY-MM-DD format. Default: today.'],
                    ],
                ],
            ],
            [
                'name' => 'get_unpaid_invoices',
                'description' => 'List students who have unpaid or partially paid fee invoices. Returns student name, class, outstanding balance, and invoice status. Use this for questions about fees owed, defaulters, outstanding balances, or students who have not paid.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'class_name' => ['type' => 'string', 'description' => 'Filter by class name (optional). E.g. "Darasa la 4", "Form 2".'],
                        'status' => ['type' => 'string', 'enum' => ['unpaid', 'partial', 'both'], 'description' => 'Filter by status. Default: both.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max results to return. Default: 20.'],
                    ],
                ],
            ],
            [
                'name' => 'get_invoice_stats',
                'description' => 'Get summary counts and totals for all invoices: how many are paid, unpaid, partial, total invoiced amount, total collected, total outstanding. Use for questions about fee collection rate, how much is owed overall, billing summary.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'term_id' => ['type' => 'integer', 'description' => 'Filter by a specific term ID (optional).'],
                        'academic_year_id' => ['type' => 'integer', 'description' => 'Filter by a specific academic year ID (optional).'],
                    ],
                ],
            ],
            [
                'name' => 'get_expense_summary',
                'description' => 'Get school spending for a date range: approved total, how much was paid now versus bought on credit (still owed to suppliers), what is waiting for approval, rejected count, and a breakdown by category, vendor, payment mode or month. Only approved expenses count as spent. Use for questions about how money was spent, operational costs, expense breakdown, biggest vendors.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD. Default: first day of current month.'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD. Default: today.'],
                        'group_by' => ['type' => 'string', 'enum' => ['category', 'vendor', 'payment_mode', 'month'], 'description' => 'How to break the total down. Default: category.'],
                        'category_name' => ['type' => 'string', 'description' => 'Only this expense category (optional, partial name).'],
                        'vendor' => ['type' => 'string', 'description' => 'Only this vendor or supplier (optional, partial name).'],
                    ],
                ],
            ],
            [
                'name' => 'get_expenses_list',
                'description' => 'List individual expense records, newest first: description, amount, vendor, category, receipt reference, payment (paid or on credit), status (pending, approved, rejected), who recorded and decided it, and the rejection reason. Use for "which expenses wait for approval", "show rejected expenses", "what did we buy from X", or finding one expense.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected'], 'description' => 'Filter by status (optional). pending = waiting for the owner to approve.'],
                        'payment_mode' => ['type' => 'string', 'enum' => ['paid', 'credit'], 'description' => 'paid = settled when bought; credit = bought on credit from a supplier.'],
                        'search' => ['type' => 'string', 'description' => 'Text in the description, vendor, receipt reference or notes (optional).'],
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD (optional).'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD (optional).'],
                        'limit' => ['type' => 'integer', 'description' => 'How many to return, default 15, max 50.'],
                    ],
                ],
            ],
            [
                'name' => 'get_petty_cash_summary',
                'description' => 'Get petty cash opening balance, total spend, and remaining balance. Use when asked about petty cash, small purchases, or cash float.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD.'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD.'],
                    ],
                ],
            ],
            [
                'name' => 'get_recent_payments',
                'description' => 'List the most recent fee payments received. Returns student name, amount, method, and date. Use when asked about latest transactions, recent payments, or today\'s collections.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Number of recent payments to return. Default: 10.'],
                        'date' => ['type' => 'string', 'description' => 'Filter by specific date YYYY-MM-DD (optional). Use "today" for today.'],
                    ],
                ],
            ],
            [
                'name' => 'get_fee_structures',
                'description' => 'List all fee structures and their items (tuition, boarding, activity fees etc.) for the current or specified term. Use when asked about what fees are charged, fee items, tuition amounts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'term_id' => ['type' => 'integer', 'description' => 'Filter by term ID (optional, defaults to current term).'],
                    ],
                ],
            ],
        ];
    }
}
