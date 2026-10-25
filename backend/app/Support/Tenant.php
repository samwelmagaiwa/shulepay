<?php

namespace App\Support;

use App\Models\School;

/**
 * "Which school am I in" for code that was written to run either on one shared database
 * (rows carry school_id) or on a database per school (no school_id, the database is the school).
 *
 * This app is the shared-database kind, so there is never a tenant database: active() is
 * always false, rows keep their school_id, and the school comes from the active-school
 * binding that the SetActiveSchool middleware makes from the X-School-Id header.
 */
final class Tenant
{
    /** Always false here — there is no per-school database. */
    public static function active(): bool
    {
        return false;
    }

    /** The school the request is working in, or null in "all schools" mode. */
    public static function school(): ?School
    {
        return app()->bound('active_school') ? app('active_school') : null;
    }

    /** The signed-in user's id, for "recorded by / approved by" columns. */
    public static function actorId(): ?int
    {
        return auth()->id();
    }

    /** A tenant database has no school_id column; here the row keeps it. */
    public static function stripSchoolId(array $attributes): array
    {
        return $attributes;
    }

    /** The school id to filter a cross-school list by (an explicit request), else null. */
    public static function scopeId(mixed $requested = null): ?int
    {
        return $requested !== null && $requested !== '' && (int) $requested > 0 ? (int) $requested : null;
    }
}
