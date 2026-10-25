<?php

namespace App\Support;

use App\Models\User;

/**
 * Which schools a request may read. Several models here (students, enrollments, classes,
 * attendance, refunds...) carry no automatic school scope, and the automatic one on the
 * others disappears in "all schools" mode — so code that reads across them states the
 * schools explicitly.
 */
class SchoolScope
{
    /**
     * @return list<int> the school ids to filter by; an empty list means every school (superadmin only)
     */
    public static function ids(User $user): array
    {
        if (app()->bound('active_school') && app('active_school')) {
            return [app('active_school')->id];
        }
        if ($user->isSuperAdmin()) {
            return [];
        }

        // A user tied to no school sees nothing rather than everything.
        return $user->allAccessibleSchoolIds() ?: [0];
    }
}
