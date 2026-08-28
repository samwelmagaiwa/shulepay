<?php

namespace App\Support;

use App\Models\DashboardLock;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side redaction of the dashboard's money figures.
 *
 * The point of doing this in the backend rather than hiding the numbers in the
 * browser is that a redacted figure is never sent at all — there is nothing to
 * recover from the network tab. The frontend only decides how to draw the
 * absence.
 *
 * Unlocking grants a short-lived pass held in the cache, not a session flag, so
 * it expires on its own even if the tab is left open.
 */
class DashboardPrivacy
{
    /** How long one successful unlock lasts. */
    public const GRANT_MINUTES = 5;

    /**
     * Keys blanked while locked.
     *
     * Chosen by VALUE, not by which card shows them: /dashibodi renders the same
     * collections and debt from total_collected_cents / total_outstanding_cents,
     * so redacting only the keys the /dashboard cards read would leave the lock
     * bypassable by opening the other page.
     */
    public const MONEY_KEYS = [
        'total_collected_cents',
        'total_outstanding_cents',
        'today_collections',
        'yesterday_collections',
        'paid_partial_invoices',
        'paid_partial_amount_cents',
        'class_fee_breakdown_cents',
        'paid_amount_cents',
        'total_expenses_cents',
        'collection_rate',
        // The trends plot the same amounts day by day and month by month — the
        // final bar of weekly_trend IS today's collections, so leaving these
        // would have shown in a chart exactly what the card above it hides.
        'weekly_trend',
        'payment_trend',
        'method_breakdown',
        // Both name a student next to an amount they paid or owe.
        'recent_payments',
        'top_debtors',
        // Deliberately absent: class_breakdown and school_breakdown hold
        // enrollment counts, and total_students / sponsored_free_count are
        // headcounts. The lock hides money, not the school.
    ];

    public static function lockFor(User $user): ?DashboardLock
    {
        return DashboardLock::where('user_id', $user->id)->first();
    }

    /** True when this user's figures must be withheld right now. */
    public static function isLocked(User $user): bool
    {
        $lock = self::lockFor($user);

        return $lock !== null && $lock->isActive() && ! self::hasGrant($user);
    }

    public static function hasGrant(User $user): bool
    {
        return Cache::get(self::grantKey($user)) === true;
    }

    public static function grant(User $user): void
    {
        Cache::put(self::grantKey($user), true, now()->addMinutes(self::GRANT_MINUTES));
    }

    public static function revokeGrant(User $user): void
    {
        Cache::forget(self::grantKey($user));
    }

    /**
     * Blank the money keys and flag the payload as locked. Headcounts and every
     * other key pass through untouched — the lock hides amounts, not the school.
     */
    public static function redact(array $stats): array
    {
        foreach (self::MONEY_KEYS as $key) {
            if (array_key_exists($key, $stats)) {
                $stats[$key] = is_array($stats[$key]) ? [] : null;
            }
        }

        $stats['locked'] = true;

        return $stats;
    }

    private static function grantKey(User $user): string
    {
        return "dashboard_unlock:{$user->id}";
    }
}
