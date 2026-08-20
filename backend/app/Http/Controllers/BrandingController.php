<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    // ── GET /api/v1/branding?school_id=X ────────────────────────────────────
    // Superadmin can pass ?school_id=X to fetch a specific school's branding.
    // Otherwise resolves the authenticated user's school → system → defaults.
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $system = SystemSetting::get('branding', []) ?? [];

        if (! $user) {
            return response()->json($this->resolve($system, null));
        }

        // Superadmin fetching a specific school's branding
        if ($user->hasRole('superadmin') && $request->filled('school_id')) {
            $school = School::find((int) $request->school_id);
            if (! $school) {
                return response()->json(['message' => 'School not found.'], 404);
            }
            $schoolBranding = ($school->settings ?? [])['branding'] ?? [];

            return response()->json(array_merge(
                $this->resolve($schoolBranding, $system, $school->name),
                ['school_id' => $school->id, 'school_name' => $school->name]
            ));
        }

        $school = $user->school;

        if (! $school) {
            return response()->json($this->resolve($system, null));
        }

        $schoolBranding = ($school->settings ?? [])['branding'] ?? [];

        return response()->json($this->resolve($schoolBranding, $system, $school->name));
    }

    // ── POST /api/v1/branding ────────────────────────────────────────────────
    // Superadmin + school_id  → writes that school's branding
    // Superadmin (no school)  → writes system-wide defaults
    // Owner                   → writes their own school's branding
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403,
            'Only owners and superadmins can update branding.'
        );

        $validated = $request->validate([
            'school_id' => 'sometimes|integer|exists:schools,id',
            'app_name' => 'sometimes|string|max:80',
            'app_tagline' => 'sometimes|string|max:80',
            'logo' => 'sometimes|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ]);

        if ($user->hasRole('superadmin')) {
            if ($request->filled('school_id')) {
                $school = School::findOrFail($request->school_id);

                return $this->updateSchool($request, $validated, $school, asSuperadmin: true);
            }

            return $this->updateSystem($request, $validated);
        }

        $school = $user->school;
        abort_if(! $school, 422, 'No school is associated with your account.');

        return $this->updateSchool($request, $validated, $school);
    }

    // ── DELETE /api/v1/branding/logo ─────────────────────────────────────────
    // Superadmin + ?school_id → removes logo for that school
    // Superadmin (no school)  → removes system logo
    // Owner                   → removes their school's logo
    public function deleteLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403
        );

        if ($user->hasRole('superadmin')) {
            if ($request->filled('school_id')) {
                $school = School::findOrFail($request->school_id);

                return $this->removeSchoolLogo($school);
            }

            // Remove system logo
            $branding = SystemSetting::get('branding', []);
            if (isset($branding['logo_path'])) {
                Storage::disk('public')->delete($branding['logo_path']);
                unset($branding['logo_path']);
            }
            SystemSetting::set('branding', $branding);

            return response()->json(['message' => 'Logo removed.', 'logo_url' => null]);
        }

        $school = $user->school;
        abort_if(! $school, 422, 'No school is associated with your account.');

        return $this->removeSchoolLogo($school);
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
                Storage::disk('public')->delete($branding['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store('branding/system', 'public');
        }

        SystemSetting::set('branding', $branding);

        return response()->json(array_merge(
            $this->resolve($branding, null),
            ['message' => 'System branding updated successfully.']
        ));
    }

    private function updateSchool(Request $request, array $validated, School $school, bool $asSuperadmin = false): JsonResponse
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
                Storage::disk('public')->delete($branding['logo_path']);
            }
            $branding['logo_path'] = $request->file('logo')->store("branding/{$school->id}", 'public');
        }

        $settings['branding'] = $branding;
        $school->update(['settings' => $settings]);

        $system = SystemSetting::get('branding', []);
        $response = array_merge(
            $this->resolve($branding, $system, $school->name),
            ['message' => 'Branding updated successfully.']
        );

        if ($asSuperadmin) {
            $response['school_id'] = $school->id;
            $response['school_name'] = $school->name;
        }

        return response()->json($response);
    }

    private function removeSchoolLogo(School $school): JsonResponse
    {
        $settings = $school->settings ?? [];
        $branding = $settings['branding'] ?? [];
        if (isset($branding['logo_path'])) {
            Storage::disk('public')->delete($branding['logo_path']);
            unset($branding['logo_path']);
        }
        $settings['branding'] = $branding;
        $school->update(['settings' => $settings]);

        return response()->json(['message' => 'Logo removed.', 'logo_url' => null]);
    }

    /**
     * Merge school branding over system branding, falling back to hard defaults.
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

        if ($logoPath) {
            // Old uploads were stored as "public/branding/..." on the local disk.
            // Strip the leading "public/" so the URL resolves correctly under /storage/.
            $logoPath = preg_replace('#^public/#', '', $logoPath);
        }

        return [
            'app_name' => $appName,
            'app_tagline' => $appTagline,
            'logo_url' => $logoPath ? '/storage/'.$logoPath : null,
        ];
    }
}
