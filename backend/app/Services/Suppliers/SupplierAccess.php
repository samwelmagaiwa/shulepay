<?php

namespace App\Services\Suppliers;

/**
 * Who may do what in the supplier module.
 *
 * Owner (school admin) and the platform superadmin can do everything. The accountant can do
 * everyday work (suppliers, bills, payments within the limit, goods received, documents) and may
 * be given the sensitive actions one by one in the school's supplier settings. Anyone else has none.
 */
final class SupplierAccess
{
    /** Every action the accountant's access can be switched for. */
    public const ACTIONS = [
        'bill_approve',        // approve or reject a supplier bill
        'payment_approve',     // approve or reject a payment above the limit; own payments are then never held back
        'payment_void',        // void an approved payment
        'bill_cancel',         // cancel a bill nobody has paid
        'grn_void',            // void a goods-received note (takes the stock back out)
        'supplier_merge',      // fold a duplicate supplier into another
        'supplier_deactivate', // put on hold, deactivate, reactivate or delete a supplier
        'document_delete',     // delete a supplier document
    ];

    public static function isOwner(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('owner') || $user->hasRole('superadmin')));
    }

    public static function isAccountant(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasRole('accountant'));
    }

    public static function can(string $action): bool
    {
        if (self::isOwner()) {
            return true;
        }

        return self::isAccountant() && (bool) (SupplierSettings::get('accountant_can')[$action] ?? false);
    }

    public static function authorize(string $action): void
    {
        abort_unless(self::can($action), 403, __('suppliers.err.not_allowed'));
    }

    /** @return array<string, bool> what the signed-in user may do, for the screens to show or hide actions */
    public static function map(): array
    {
        return collect(self::ACTIONS)->mapWithKeys(fn ($a) => [$a => self::can($a)])->all();
    }
}
