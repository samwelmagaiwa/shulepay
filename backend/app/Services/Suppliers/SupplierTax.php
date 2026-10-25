<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;

/**
 * VAT on bills and withholding tax (WHT) on payments, from the school's own rates.
 * All money is integer cents; rounding is to the nearest cent.
 */
final class SupplierTax
{
    public const WHT_TYPES = ['goods', 'services'];

    /** VAT charged on a subtotal, but only if the supplier is VAT-registered. */
    public static function vat(int $subtotalCents, Supplier $supplier): int
    {
        if (! $supplier->vat_registered) {
            return 0;
        }

        return (int) round($subtotalCents * (float) SupplierSettings::get('vat_rate') / 100);
    }

    /** The WHT suggested for paying this supplier $amountCents; the payer may override it. */
    public static function suggestWht(Supplier $supplier, int $amountCents): int
    {
        if (! $supplier->wht_applicable || $amountCents < (int) SupplierSettings::get('wht_threshold_cents')) {
            return 0;
        }

        $key = $supplier->wht_type === 'services' ? 'wht_services_rate' : 'wht_goods_rate';

        return (int) round($amountCents * (float) SupplierSettings::get($key) / 100);
    }
}
