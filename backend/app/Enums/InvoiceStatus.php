<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Amelipa',
            self::Partial => 'Amelipa kiasi',
            self::Unpaid => 'Hajalipa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'amber',
            self::Unpaid => 'red',
        };
    }
}
