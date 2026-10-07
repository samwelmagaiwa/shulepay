<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\OtpCode;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserSettingsController extends Controller
{
    public function __construct(private SmsService $sms) {}

    /**
     * GET /api/settings/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $lastLogin = LoginHistory::where('user_id', $user->id)
            ->where('status', 'success')
            ->latest('attempted_at')
            ->skip(1)  // skip current session
            ->first();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->getRoleNames()->first(),
            '2fa_enabled' => (bool) $user->{'2fa_enabled'},
            'last_login' => $lastLogin?->attempted_at,
        ]);
    }

    /**
     * PUT /api/settings/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $rules = [
            'name' => 'sometimes|string|max:255',
            'phone' => ['sometimes', 'string', 'regex:/^(\+?255|0)[67]\d{8}$/'],
        ];

        // Password change requires current_password
        if ($request->filled('password')) {
            $rules['current_password'] = 'required|string';
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $data = $request->validate($rules);

        if (isset($data['current_password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => [__('The current password is incorrect.')],
                ]);
            }
            unset($data['current_password']);
        }

        $user->update($data);

        return response()->json(['message' => __('Your details have been updated.'), 'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]]);
    }

    /**
     * POST /api/settings/change-password
     * Works for both forced first-login change and voluntary profile change.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        return response()->json(['message' => __('Your password has been changed.')]);
    }

    /**
     * POST /api/settings/toggle-2fa
     * Disabling takes effect immediately. Enabling only sends a verification OTP —
     * 2fa_enabled is NOT flipped here. It previously was flipped unconditionally on
     * this same request, which sent an OTP that nothing ever checked, making 2FA
     * enable functionally unprotected by its own verification step. The frontend
     * must call verify-2fa-enable with the code to actually complete enabling.
     */
    public function toggle2fa(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = (bool) $user->{'2fa_enabled'};

        if ($current) {
            $user->update(['2fa_enabled' => false]);

            return response()->json(['enabled' => false, 'message' => __('Two-factor authentication has been turned off.')]);
        }

        if (! $user->phone) {
            return response()->json(['message' => __('You need a phone number to turn on two-factor authentication.')], 422);
        }

        OtpCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $request->ip(),
        ]);
        try {
            $this->sms->send($user->phone, "ShulePay: Uthibitisho wa kuwasha 2FA. Msimbo: {$code}");
        } catch (\Throwable) {
        }

        return response()->json([
            'enabled' => false,
            'requires_verification' => true,
            'message' => __('A verification code has been sent. Enter it to turn on two-factor authentication.'),
        ]);
    }

    /**
     * POST /api/settings/verify-2fa-enable
     * Completes enabling 2FA — call after toggle-2fa sends the OTP.
     */
    public function verify2faEnable(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $otp = OtpCode::valid()
            ->where('user_id', $user->id)
            ->where('code', $request->code)
            ->latest()
            ->first();

        if (! $otp) {
            return response()->json(['message' => __('The code is incorrect or has expired.')], 422);
        }

        $otp->update(['used_at' => now()]);
        $user->update(['2fa_enabled' => true]);

        return response()->json(['enabled' => true, 'message' => __('Two-factor authentication has been turned on.')]);
    }
}
