<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    // ── GET /api/v1/branding ─────────────────────────────────────────────────
    // Returns the most-specific branding available:
    //   school branding → system branding → hard defaults
    public function show(Request $request): JsonResponse
    {
        $system = SystemSetting::get('branding', []);
        $school = $request->user()?->school;

        if (! $school) {
            return response()->json($this->resolve($system, null));
        }

        $schoolBranding = ($school->settings ?? [])['branding'] ?? [];

        return response()->json($this->resolve($schoolBranding, $system, $school->name));
    }

    // ── POST /api/v1/branding ────────────────────────────────────────────────
    // Superadmin → writes system-wide defaults (applies to all schools that
    //   have not overridden their own branding).
    // Owner      → writes their school's branding only.
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403,
            'Only owners and superadmins can update branding.'
        );

        $validated = $request->validate([
            'app_name' => 'sometimes|string|max:80',
            'app_tagline' => 'sometimes|string|max:80',
            'logo' => 'sometimes|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ]);

        if ($user->hasRole('superadmin')) {
            return $this->updateSystem($request, $validated);
        }

        $school = $user->school;
        abort_if(! $school, 422, 'No school is associated with your account.');

        return $this->updateSchool($request, $validated, $school);
    }

    // ── DELETE /api/v1/branding/logo ─────────────────────────────────────────
    public function deleteLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403
        );

        if ($user->hasRole('superadmin')) {
            $branding = SystemSetting::get('branding', []);
            if (isset($branding['logo_path'])) {
                Storage::delete($branding['logo_path']);
                unset($branding['logo_path']);
            }
            SystemSetting::set('branding', $branding);

            return response()->json(['message' => 'Logo removed.', 'logo_url' => null]);
        }

        $school = $user->school;
        abort_if(! $school, 422, 'No school is associated with your account.');

        $settings = $school->settings ?? [];
        $branding = $settings['branding'] ?? [];
        if (isset($branding['logo_path'])) {
            Storage::delete($branding['logo_path']);
            unset($branding['logo_path']);
        }
        $settings['branding'] = $branding;
        $school->update(['settings' => $settings]);

        return response()->json(['message' => 'Logo removed.', 'logo_url' => null]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function updateSystem(Request $request, array $validated): JsonResponse
    {
        $branding = SystemSetting::get('branding', []);

        if (isset($validated['app_name'])) {
            $branding['app_name'] = $validated['app_name'];
        }
        if (isset($validated['app_tagline'])) {
            $branding['app_tagline'] = $validated['app_tagline'];
        }

        if ($request->hasFile('logo')) {
            if (isset($branding['logo_path'])) {
                Storage::delete($branding['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store('public/branding/system');
        }

        SystemSetting::set('branding', $branding);

        return response()->json(array_merge(
            $this->resolve($branding, null),
            ['message' => 'System branding updated successfully.']
        ));
    }

    private function updateSchool(Request $request, array $validated, $school): JsonResponse
    {
        $settings = $school->settings ?? [];
        $branding = $settings['branding'] ?? [];

        if (isset($validated['app_name'])) {
            $branding['app_name'] = $validated['app_name'];
        }
        if (isset($validated['app_tagline'])) {
            $branding['app_tagline'] = $validated['app_tagline'];
        }

        if ($request->hasFile('logo')) {
            if (isset($branding['logo_path'])) {
                Storage::delete($branding['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store("public/branding/{$school->id}");
        }

        $settings['branding'] = $branding;
        $school->update(['settings' => $settings]);

        $system = SystemSetting::get('branding', []);

        return response()->json(array_merge(
            $this->resolve($branding, $system, $school->name),
            ['message' => 'Branding updated successfully.']
        ));
    }

    /**
     * Merge school branding over system branding, falling back to hard defaults.
     * $school is used only as the fallback app_name when neither layer has one.
     */
    private function resolve(array $specific, ?array $system, ?string $schoolName = null): array
    {
        $system = $system ?? [];

        $appName = $specific['app_name']
            ?? $system['app_name']
            ?? $schoolName
            ?? 'ShulePay';

        $appTagline = $specific['app_tagline']
            ?? $system['app_tagline']
            ?? 'nexoryaTECH';

        $logoPath = $specific['logo_path'] ?? $system['logo_path'] ?? null;

        return [
            'app_name' => $appName,
            'app_tagline' => $appTagline,
            'logo_url' => $logoPath ? Storage::url($logoPath) : null,
        ];
    }
}
