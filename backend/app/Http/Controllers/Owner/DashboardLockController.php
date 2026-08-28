<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\DashboardLock;
use App\Support\DashboardPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Set / verify / remove a user's dashboard privacy code.
 *
 * Every lock is the acting user's own — the queries below are all keyed on
 * the request user, so there is no route by which one user reaches another's lock.
 */
class DashboardLockController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $lock = DashboardPrivacy::lockFor($user);

        return response()->json([
            'configured' => $lock !== null,
            'locked' => DashboardPrivacy::isLocked($user),
            'unlocked_until' => DashboardPrivacy::hasGrant($user)
                ? now()->addMinutes(DashboardPrivacy::GRANT_MINUTES)->toIso8601String()
                : null,
        ]);
    }

    /** Set the code for the first time, or re-lock with the code already set. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $lock = DashboardPrivacy::lockFor($user);

        if ($lock === null) {
            $request->validate([
                'code' => ['required', 'string', 'min:4', 'max:12', 'confirmed'],
            ]);

            $lock = DashboardLock::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($request->string('code')->toString()),
            ]);
        }

        // Re-locking must not silently accept a new code — that would let anyone
        // at an unlocked screen overwrite the owner's code with their own.
        $lock->update(['locked_at' => now()]);
        DashboardPrivacy::revokeGrant($user);

        return response()->json(['configured' => true, 'locked' => true]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();
        $lock = DashboardPrivacy::lockFor($user);

        if ($lock === null) {
            return response()->json(['message' => 'Hakuna msimbo uliowekwa.'], 422);
        }

        // A 4-digit code is brute-forceable in seconds without this.
        $throttle = 'dashboard-unlock:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttle, 5)) {
            return response()->json([
                'message' => 'Umejaribu mara nyingi. Subiri sekunde '.RateLimiter::availableIn($throttle).'.',
            ], 429);
        }

        if (! Hash::check($request->string('code')->toString(), $lock->code_hash)) {
            RateLimiter::hit($throttle, 60);

            return response()->json(['message' => 'Msimbo si sahihi.'], 422);
        }

        RateLimiter::clear($throttle);
        DashboardPrivacy::grant($user);

        return response()->json([
            'locked' => false,
            'unlocked_until' => now()->addMinutes(DashboardPrivacy::GRANT_MINUTES)->toIso8601String(),
        ]);
    }

    /**
     * Remove the lock entirely. Gated on the account password rather than the
     * code, so a forgotten code is recoverable without a support request.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Nenosiri si sahihi.'], 422);
        }

        DashboardPrivacy::lockFor($user)?->delete();
        DashboardPrivacy::revokeGrant($user);

        return response()->json(['configured' => false, 'locked' => false]);
    }
}
