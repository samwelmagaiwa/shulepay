<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;

class SetActiveSchool
{
    /**
     * Routes that may read any school's data without the school-access gate.
     * Branding (name, tagline, logo) is display-only and not sensitive.
     */
    private const OPEN_READ_PATHS = [
        'api/branding',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // Priority: explicit header/param > user's own school
        $hasExplicit = $request->hasHeader('X-School-Id') || $request->has('school_id');
        $schoolId = $request->header('X-School-Id') ?? $request->query('school_id');

        // Check whether this path is exempt from the school-access gate
        $isOpenRead = $request->isMethod('GET')
            && collect(self::OPEN_READ_PATHS)->contains(
                fn ($p) => str_starts_with(ltrim($request->path(), '/'), $p)
            );

        if ($hasExplicit) {
            $val = $request->header('X-School-Id') ?? $request->query('school_id');
            // Explicit "0" / "" / "all" → "Shule Zote" mode: no active_school, scope is skipped
            if ($val !== null && $val !== '' && $val !== '0' && $val !== 'all' && (int) $val > 0) {
                $schoolId = (int) $val;
                $school = School::find($schoolId);
                if ($school) {
                    // Enforce access gate — unless the path is an open-read route
                    if (! $isOpenRead && ($user = auth('sanctum')->user())) {
                        if (! $user->canAccessSchool($schoolId)) {
                            return response()->json(['message' => 'Huna ruhusa ya kufikia shule hii.'], 403);
                        }
                    }
                    app()->instance('active_school', $school);
                }
            }
            // else: Shule Zote — leave active_school unbound
        } else {
            // No explicit param → fall back to the authenticated user's school
            if ($user = auth('sanctum')->user()) {
                if ($user->hasRole('superadmin')) {
                    // Superadmin without explicit school param defaults to all schools
                } elseif ($user->school_id && $school = School::find($user->school_id)) {
                    app()->instance('active_school', $school);
                }
            }
        }

        return $next($request);
    }
}
