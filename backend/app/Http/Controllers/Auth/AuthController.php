<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Highest privilege wins when a user has multiple Spatie roles
    private const ROLE_PRIORITY = [
        'superadmin', 'owner', 'accountant',
        'head_teacher', 'headmaster', 'academic_pri', 'academic_sec',
        'teacher_pri', 'teacher_sec', 'teacher', 'academic_teacher',
        'parent',
    ];

    private function primaryRole(User $user): string
    {
        $names = $user->getRoleNames()->all();
        foreach (self::ROLE_PRIORITY as $role) {
            if (in_array($role, $names)) {
                return $role;
            }
        }

        return $names[0] ?? 'unknown';
    }

    /** Public endpoint — returns schools list for login dropdown */
    public function schools(): JsonResponse
    {
        $schools = School::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'level']);

        return response()->json($schools);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
            'school_id' => 'nullable|integer|exists:schools,id',
        ]);

        // Find user for logging purposes (even on failure)
        $foundUser = User::where('email', $request->email)->first();

        if (! Auth::attempt($request->only('email', 'password'))) {
            LoginHistory::create([
                'user_id' => $foundUser?->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'attempted_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Barua pepe au neno la siri si sahihi.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Block deactivated accounts immediately after credential check
        if (! $user->is_active) {
            Auth::logout();
            LoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'attempted_at' => now(),
            ]);
            throw ValidationException::withMessages([
                'email' => ['Akaunti yako imezuiwa. Wasiliana na msimamizi.'],
            ]);
        }

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
            'attempted_at' => now(),
        ]);

        // Validate selected school access
        if ($request->filled('school_id')) {
            $schoolId = (int) $request->school_id;
            if (! $user->canAccessSchool($schoolId)) {
                Auth::logout();
                $schoolName = School::find($schoolId)?->name ?? 'hiyo shule';
                throw ValidationException::withMessages([
                    'school_id' => ["Huna ruhusa ya kuingia {$schoolName}. Wasiliana na msimamizi."],
                ]);
            }
        }

        // If 2FA is enabled, do not issue a token yet — ask frontend to verify OTP
        if ($user->{'2fa_enabled'}) {
            Auth::logout(); // un-auth the session guard; Sanctum is stateless but guard may be set

            return response()->json([
                'requires_2fa' => true,
                'user_id' => $user->id,
            ]);
        }

        $token = $user->createToken('shulepay')->plainTextToken;

        // Resolve the active school for this session
        $selectedSchoolId = $request->filled('school_id')
            ? (int) $request->school_id
            : $user->school_id;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $this->primaryRole($user),
                'school_id' => $selectedSchoolId,
                'permissions' => $user->effectivePermissions(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Umefanikiwa kutoka.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('school');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'school_id' => $user->school_id,
            'school' => $user->school?->only(['id', 'name', 'level']),
            'permissions'            => $user->effectivePermissions(),
            'accessible_school_ids'  => $user->hasRole('superadmin') ? null : $user->allAccessibleSchoolIds(),
        ]);
    }
}
