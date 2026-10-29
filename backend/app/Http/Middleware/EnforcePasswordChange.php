<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * An account still on its initial password may do nothing but change it (or sign out / read itself).
 * The app already sends such users to the change-password screen; this makes the server refuse
 * everything else, so the initial password alone never opens any data through the API.
 */
class EnforcePasswordChange
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('sanctum')->user();

        if ($user && $user->must_change_password
            && ! $request->is('api/auth/*', 'api/settings/change-password')) {
            return response()->json([
                'message' => __('You must change your password before continuing.'),
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
