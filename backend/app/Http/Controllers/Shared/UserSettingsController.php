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
                    'current_password' => ['Neno la siri la sasa si sahihi.'],
                ]);
            }
            unset($data['current_password']);
        }

        $user->update($data);

        return response()->json(['message' => 'Taarifa zimesasishwa.', 'user' => [
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
                'current_password' => ['Neno la siri la sasa si sahihi.'],
            ]);
        }

        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Neno la siri limebadilishwa.']);
    }

    /**
     * POST /api/settings/toggle-2fa
     */
    public function toggle2fa(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = (bool) $user->{'2fa_enabled'};

        if (! $current && ! $user->phone) {
            return response()->json(['message' => 'Lazima uwe na nambari ya simu ili kuwasha 2FA.'], 422);
        }

        if (! $current) {
            // Enabling — send a verification OTP first
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
        }

        $user->update(['2fa_enabled' => ! $current]);

        return response()->json([
            'enabled' => ! $current,
            'message' => ! $current ? '2FA imewashwa.' : '2FA imezimwa.',
        ]);
    }
}
