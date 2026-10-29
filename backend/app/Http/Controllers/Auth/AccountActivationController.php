<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Public steps for a new account: (1) get a code by SMS, (2) enter it, (3) choose a password.
 * Answers never reveal whether an email address or phone number has an account.
 */
class AccountActivationController extends Controller
{
    public function __construct(private readonly AccountActivationService $activation) {}

    /** POST /api/auth/activate/resend */
    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:120']]);

        $this->activation->resend($data['identifier']);

        return response()->json(['message' => __('If this account is waiting to be activated, a new code has been sent by SMS.')]);
    }

    /** POST /api/auth/activate/verify */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'code' => ['required', 'digits:6'],
        ]);

        $token = $this->activation->verify($data['identifier'], $data['code']);

        if (! $token) {
            return response()->json(['message' => __('The code is incorrect or has expired. Request a new code if needed.')], 422);
        }

        return response()->json(['message' => __('Code accepted. Now choose your password.'), 'token' => $token, 'expires_in' => AccountActivationService::TOKEN_MINUTES * 60]);
    }

    /** POST /api/auth/activate/complete */
    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ], [
            'password.confirmed' => __('The two passwords do not match.'),
        ]);

        $user = $this->activation->complete($data['token'], $data['password']);

        if (! $user) {
            return response()->json(['message' => __('This step has expired. Start again with a new code.'), 'code' => 'ACTIVATION_EXPIRED'], 422);
        }

        return response()->json(['message' => __('Your account is ready. You can sign in now.')]);
    }
}
