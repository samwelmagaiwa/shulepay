<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case Bank = 'bank';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Taslimu',
            self::Mpesa => 'M-Pesa',
            self::Bank => 'Benki',
            self::Cheque => 'Hundi',
        };
    }
}
