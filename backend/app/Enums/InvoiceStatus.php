<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    /** Display name in the language of the current request. */
    public function label(): string
    {
        return __('enums.invoice_status.'.$this->value);
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
