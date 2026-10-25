<?php

namespace App\Ai\Tools;

class SupplierTools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'get_supplier_outstanding',
                'description' => 'Get suppliers with unpaid bills: supplier name, total owed, number of outstanding bills. Use when asked about supplier debts, what the school owes suppliers, accounts payable.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 10.'],
                    ],
                ],
            ],
            [
                'name' => 'get_supplier_spend',
                'description' => 'Get total amount paid to suppliers for a period, with breakdown by supplier or category. Use when asked about procurement spend, supplier payments, purchasing costs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD.'],
                        'to_date' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD.'],
                    ],
                ],
            ],
            [
                'name' => 'get_recent_purchases',
                'description' => 'List recent goods receipts/purchases: what was ordered, from which supplier, amount, and date. Use when asked about recent orders, what has been purchased.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max results. Default: 10.'],
                    ],
                ],
            ],
        ];
    }
}
