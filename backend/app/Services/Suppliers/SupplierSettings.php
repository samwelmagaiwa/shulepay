<?php

namespace App\Services\Suppliers;

use App\Support\Tenant;

/**
 * The school's own purchasing rules, kept in School::settings['suppliers'].
 *
 * What a school can tune: the amount an accountant may pay without approval, the tax rates, and
 * which of the sensitive actions its accountant may do (see SupplierAccess). The owner can always
 * do everything; changing these settings is the owner's alone.
 */
final class SupplierSettings
{
    public const DEFAULTS = [
        // Payments up to this need no approval when someone without payment-approval rights records them (TZS 500,000).
        'payment_approval_limit_cents' => 50_000_000,
        'vat_rate' => 18.0,
        'wht_goods_rate' => 2.0,
        'wht_services_rate' => 5.0,
        // WHT only applies from this payment size upward (TZS 1,000,000).
        'wht_threshold_cents' => 100_000_000,
        // What the accountant may do. Out of the box approvals and voids stay with the owner.
        'accountant_can' => [
            'bill_approve' => false,
            'payment_approve' => false,
            'payment_void' => false,
            'bill_cancel' => true,
            'grn_void' => true,
            'supplier_merge' => false,
            'supplier_deactivate' => true,
            'document_delete' => true,
        ],
    ];

    public static function all(): array
    {
        $stored = Tenant::school()?->settings['suppliers'] ?? [];
        $merged = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        // A switch the school never touched keeps its default, so new switches added later arrive with a sensible value.
        $merged['accountant_can'] = array_map('boolval', array_merge(
            self::DEFAULTS['accountant_can'],
            array_intersect_key((array) ($stored['accountant_can'] ?? []), self::DEFAULTS['accountant_can']),
        ));

        return $merged;
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function save(array $values): array
    {
        $school = Tenant::school();
        $settings = $school->settings ?? [];
        $current = self::all();

        $next = array_merge($current, array_intersect_key($values, self::DEFAULTS));
        if (isset($values['accountant_can'])) {
            $next['accountant_can'] = array_map('boolval', array_merge(
                $current['accountant_can'],
                array_intersect_key((array) $values['accountant_can'], self::DEFAULTS['accountant_can']),
            ));
        }

        $settings['suppliers'] = $next;
        $school->update(['settings' => $settings]);

        return self::all();
    }
}
