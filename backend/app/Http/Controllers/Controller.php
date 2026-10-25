<?php

namespace App\Http\Controllers;

use App\Support\SchoolScope;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Code written for either one shared database or a database per school asks this before it
     * filters by school_id. This app is the shared kind, so the answer is always no.
     */
    protected function isTenantMode(): bool
    {
        return false;
    }

    /**
     * The school a list or a create should work in: the one named in the request (only if the user may
     * work there), else the active school, else the user's own. Null means no single school.
     */
    protected function schoolIdForQuery(Request $request): ?int
    {
        if ($request->filled('school_id') && (int) $request->school_id > 0) {
            SchoolScope::authorize($request->user(), (int) $request->school_id);

            return (int) $request->school_id;
        }
        if (app()->bound('active_school') && app('active_school')) {
            return app('active_school')->id;
        }

        return $request->user()?->school_id ?: null;
    }
}
