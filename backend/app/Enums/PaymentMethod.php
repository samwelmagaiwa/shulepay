<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case Bank = 'bank';
    case Cheque = 'cheque';
    case Sponsor = 'sponsor';

    /** Display name in the language of the current request. */
    public function label(): string
    {
        return __('enums.payment_method.'.$this->value);
    }
}
