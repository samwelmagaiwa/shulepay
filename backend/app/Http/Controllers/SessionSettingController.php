<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionSettingController extends Controller
{
    private const SETTINGS_KEY = 'session_timeout';

    // Bounds guard against a mistyped value silently locking everyone out
    // (e.g. 0 minutes) or making the feature pointless (e.g. 24 hours).
    private const MIN_IDLE_MINUTES = 5;

    private const MAX_IDLE_MINUTES = 240;

    private const MIN_WARNING_SECONDS = 15;

    private const MAX_WARNING_SECONDS = 300;

    public const DEFAULTS = [
        'idle_minutes' => 30,
        'warning_seconds' => 60,
    ];

    /**
     * Every authenticated user needs these values to run their own idle
     * timer — read access carries no sensitive data, so (like branding) it
     * is not role-gated.
     */
    public function show(): JsonResponse
    {
        return response()->json($this->currentSettings());
    }

    /**
     * Writing the policy that logs everyone out is an administrative action —
     * gated the same way branding writes are (adminStaff: owner/superadmin).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idle_minutes' => ['required', 'integer', 'min:'.self::MIN_IDLE_MINUTES, 'max:'.self::MAX_IDLE_MINUTES],
            'warning_seconds' => ['required', 'integer', 'min:'.self::MIN_WARNING_SECONDS, 'max:'.self::MAX_WARNING_SECONDS],
        ]);

        SystemSetting::set(self::SETTINGS_KEY, $data);

        return response()->json($this->currentSettings());
    }

    private function currentSettings(): array
    {
        $stored = SystemSetting::get(self::SETTINGS_KEY, []) ?? [];

        // Merge over defaults rather than trust a partially-written or
        // stale row wholesale — a value missing or out of bounds falls back
        // to its default instead of producing an unusable idle timer.
        $idleMinutes = (int) ($stored['idle_minutes'] ?? self::DEFAULTS['idle_minutes']);
        $warningSeconds = (int) ($stored['warning_seconds'] ?? self::DEFAULTS['warning_seconds']);

        return [
            'idle_minutes' => $this->clamp($idleMinutes, self::MIN_IDLE_MINUTES, self::MAX_IDLE_MINUTES, self::DEFAULTS['idle_minutes']),
            'warning_seconds' => $this->clamp($warningSeconds, self::MIN_WARNING_SECONDS, self::MAX_WARNING_SECONDS, self::DEFAULTS['warning_seconds']),
            'bounds' => [
                'idle_minutes' => ['min' => self::MIN_IDLE_MINUTES, 'max' => self::MAX_IDLE_MINUTES],
                'warning_seconds' => ['min' => self::MIN_WARNING_SECONDS, 'max' => self::MAX_WARNING_SECONDS],
            ],
        ];
    }

    private function clamp(int $value, int $min, int $max, int $fallback): int
    {
        if ($value < $min || $value > $max) {
            return $fallback;
        }

        return $value;
    }
}
