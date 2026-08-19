<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private SmsService $sms) {}

    /**
     * POST /api/2fa/request
     * Generate and send a 6-digit OTP to the user's phone.
     */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        if (! $user->phone) {
            return response()->json(['message' => 'Nambari ya simu haipatikani.'], 422);
        }

        // Invalidate previous unused OTPs for this user
        OtpCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $request->ip(),
        ]);

        try {
            $message = SmsTemplates::otpCode($code, 10);
            $this->sms->send($user->phone, $message);
        } catch (\Throwable $e) {
            // SMS failure must not prevent OTP creation
        }

        return response()->json(['message' => 'Msimbo umetumwa', 'expires_in' => 600]);
    }

    /**
     * POST /api/2fa/verify
     * Verify OTP and return Sanctum token.
     * Rate-limited: 5 attempts per 15 minutes per IP (configured in RouteServiceProvider/routes).
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string|size:6',
        ]);

        $user = User::findOrFail($request->user_id);

        $otp = OtpCode::valid()
            ->where('user_id', $user->id)
            ->where('code', $request->code)
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json(['message' => 'Msimbo si sahihi au umeisha muda wake.'], 422);
        }

        $otp->update(['used_at' => now()]);

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
            ],
        ]);
    }
}
