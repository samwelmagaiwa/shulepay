<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /** GET /api/v1/branding — returns branding for the authenticated user's school */
    public function show(Request $request): JsonResponse
    {
        $school = $request->user()?->school;

        if (! $school) {
            return response()->json($this->defaults());
        }

        $settings = $school->settings ?? [];
        $branding = $settings['branding'] ?? [];

        return response()->json([
            'app_name' => $branding['app_name'] ?? $school->name,
            'app_tagline' => $branding['app_tagline'] ?? 'nexoryaTECH',
            'logo_url' => isset($branding['logo_path'])
                ? Storage::url($branding['logo_path'])
                : null,
        ]);
    }

    /** POST /api/v1/branding — update branding (owner or superadmin only) */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403,
            'Only owners and superadmins can update branding.'
        );

        $school = $user->school;
        abort_if(! $school, 422, 'No school is associated with your account.');

        $validated = $request->validate([
            'app_name' => 'sometimes|string|max:80',
            'app_tagline' => 'sometimes|string|max:80',
            'logo' => 'sometimes|file|mimes:jpg,jpeg,png,svg,webp|max:2048',
        ]);

        $settings = $school->settings ?? [];
        $branding = $settings['branding'] ?? [];

        if (isset($validated['app_name'])) {
            $branding['app_name'] = $validated['app_name'];
        }
        if (isset($validated['app_tagline'])) {
            $branding['app_tagline'] = $validated['app_tagline'];
        }

        if ($request->hasFile('logo')) {
            // Delete old logo if any
            if (isset($branding['logo_path'])) {
                Storage::delete($branding['logo_path']);
            }
            $path = $request->file('logo')->store("public/branding/{$school->id}");
            $branding['logo_path'] = $path;
        }

        $settings['branding'] = $branding;
        $school->update(['settings' => $settings]);

        return response()->json([
            'app_name' => $branding['app_name'] ?? $school->name,
            'app_tagline' => $branding['app_tagline'] ?? 'nexoryaTECH',
            'logo_url' => isset($branding['logo_path'])
                ? Storage::url($branding['logo_path'])
                : null,
            'message' => 'Branding updated successfully.',
        ]);
    }

    /** DELETE /api/v1/branding/logo — remove uploaded logo */
    public function deleteLogo(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('owner') || $user->hasRole('superadmin'),
            403
        );

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

    private function defaults(): array
    {
        return ['app_name' => 'ShulePay', 'app_tagline' => 'nexoryaTECH', 'logo_url' => null];
    }
}
