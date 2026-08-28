<?php

namespace App\Http\Middleware;

use App\Support\DashboardPrivacy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that would hand back figures the dashboard is currently hiding.
 *
 * Applied as middleware rather than inside the controller because the guarded
 * endpoints return files, not JSON — there is no clean way for them to answer
 * "locked" from inside their own return type.
 */
class EnsureDashboardUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && DashboardPrivacy::isLocked($user)) {
            return response()->json([
                'message' => 'Takwimu za fedha zimefungwa. Fungua kwa msimbo wako kwanza.',
                'locked' => true,
            ], 423);
        }

        return $next($request);
    }
}
