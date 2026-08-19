<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('sanctum')->user();

        if ($user && !$user->is_active) {
            // Revoke all tokens so re-login is forced after reactivation
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.',
                'code'    => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        return $next($request);
    }
}
