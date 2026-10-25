<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;

/** What the school owes one supplier, line by line, with a running balance. */
final class SupplierStatement
{
    public static function build(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $events = [];

        foreach (SupplierBill::where('supplier_id', $supplier->id)->whereIn('status', SupplierBill::OWED)->get() as $b) {
            $events[] = ['date' => $b->bill_date->toDateString(), 'sort' => 0, 'type' => 'bill', 'ref' => $b->bill_number,
                'description' => null, 'debit_cents' => (int) $b->total_cents, 'credit_cents' => 0, 'id' => $b->id];
        }
        foreach (SupplierPayment::where('supplier_id', $supplier->id)->where('status', SupplierPayment::APPROVED)->get() as $p) {
            $events[] = ['date' => $p->payment_date->toDateString(), 'sort' => 1, 'type' => 'payment', 'ref' => $p->reference,
                'description' => $p->method, 'debit_cents' => 0, 'credit_cents' => (int) $p->amount_cents, 'id' => $p->id];
        }
        usort($events, fn ($a, $b) => [$a['date'], $a['sort'], $a['id']] <=> [$b['date'], $b['sort'], $b['id']]);

        $running = (int) $supplier->opening_balance_cents;
        $opening = $running;
        $lines = [];
        foreach ($events as $e) {
            $running += $e['debit_cents'] - $e['credit_cents'];
            $e['balance_cents'] = $running;
            if ($from && $e['date'] < $from) {
                $opening = $running;

                continue;
            }
            if ($to && $e['date'] > $to) {
                continue;
            }
            $lines[] = $e;
        }

        return [
            'opening_balance_cents' => $opening,
            'lines' => $lines,
            'total_billed_cents' => array_sum(array_column($lines, 'debit_cents')),
            'total_paid_cents' => array_sum(array_column($lines, 'credit_cents')),
            'closing_balance_cents' => $lines ? end($lines)['balance_cents'] : $opening,
        ];
    }
}
