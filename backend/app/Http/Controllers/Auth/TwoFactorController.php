<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Second step of a sign-in for accounts with two-factor authentication.
 *
 * The first step (password) hands out a one-time `challenge`. Only with it can a code be requested or checked, so
 * nobody can make the system text a stranger's phone or try codes against a user id. Codes are stored hashed,
 * expire in 10 minutes, and five wrong tries cancel the challenge.
 */
class TwoFactorController extends Controller
{
    private const TRIES = 5;

    private const RESEND_AFTER_SECONDS = 60;

    public function __construct(private SmsService $sms) {}

    private function key(string $challenge): string
    {
        return '2fa:'.hash('sha256', $challenge);
    }

    private function userFor(string $challenge): ?User
    {
        $id = Cache::get($this->key($challenge));

        return $id ? User::find($id) : null;
    }

    /** POST /api/2fa/request — send a 6-digit code to the phone of the user who just gave the right password. */
    public function request(Request $request): JsonResponse
    {
        $request->validate(['challenge' => 'required|string|size:64']);

        $user = $this->userFor($request->challenge);
        if (! $user) {
            return response()->json(['message' => __('This sign-in has expired. Please sign in again.'), 'code' => 'TWO_FA_EXPIRED'], 422);
        }

        if (! $user->phone) {
            return response()->json(['message' => __('The phone number is not available.')], 422);
        }

        $last = OtpCode::where('user_id', $user->id)->latest()->first();
        if ($last && $last->created_at->gt(now()->subSeconds(self::RESEND_AFTER_SECONDS))) {
            return response()->json(['message' => __('A code was just sent. Please wait a minute before asking for another.')], 429);
        }

        // Invalidate previous unused OTPs for this user
        OtpCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id' => $user->id,
            'code' => OtpCode::hash($code),
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $request->ip(),
        ]);

        try {
            $this->sms->send($user->phone, SmsTemplates::otpCode($code, 10));
        } catch (\Throwable $e) {
            // SMS failure must not prevent OTP creation
        }

        return response()->json(['message' => __('The code has been sent'), 'expires_in' => 600]);
    }

    /** POST /api/2fa/verify — check the code and sign in. */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'challenge' => 'required|string|size:64',
            'code' => 'required|digits:6',
        ]);

        $user = $this->userFor($request->challenge);
        if (! $user) {
            return response()->json(['message' => __('This sign-in has expired. Please sign in again.'), 'code' => 'TWO_FA_EXPIRED'], 422);
        }

        $triesKey = '2fa-tries:'.hash('sha256', $request->challenge);
        Cache::add($triesKey, 0, now()->addMinutes(10));
        if (Cache::increment($triesKey) > self::TRIES) {
            Cache::forget($this->key($request->challenge));

            return response()->json(['message' => __('Too many wrong codes. Please sign in again.'), 'code' => 'TWO_FA_EXPIRED'], 422);
        }

        $otp = OtpCode::valid()
            ->where('user_id', $user->id)
            ->where('code', OtpCode::hash($request->code))
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json(['message' => __('The code is incorrect or has expired.')], 422);
        }

        $otp->update(['used_at' => now()]);
        Cache::forget($this->key($request->challenge));
        Cache::forget($triesKey);

        // Delete existing tokens and issue fresh one
        $user->tokens()->delete();
        $token = $user->createToken('shulepay')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'school_id' => $user->school_id,
                'permissions' => $user->effectivePermissions(),
                'accessible_school_ids' => $user->hasRole('superadmin') ? null : $user->allAccessibleSchoolIds(),
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ]);
    }
}
