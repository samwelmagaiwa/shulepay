<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        // Any authenticated user can READ branding for any active school.
        // Branding (name, tagline, logo) is not sensitive — it's displayed publicly.
        if ($request->filled('school_id')) {
            $school = School::where('id', (int) $request->school_id)
                ->where('is_active', true)
                ->first();

            if (! $school) {
                return response()->json(['message' => 'School not found.'], 404);
            }

            $schoolBranding = ($school->settings ?? [])['branding'] ?? [];

            return response()->json(array_merge(
                $this->resolve($schoolBranding, $system, $school),
                ['school_id' => $school->id, 'school_name' => $school->name]
            ));
        }

        // A superadmin who sent no school_id is on "System Default", so return the
        // system values — not their own school's. update() already routes the same
        // request to updateSystem(), and the two disagreeing meant the form showed
        // one school's details while saving somewhere else entirely.
        if ($user->hasRole('superadmin')) {
            return response()->json($this->resolve($system, null));
        }

        $school = $user->school;

        if (! $school) {
            return response()->json($this->resolve($system, null));
        }

        $schoolBranding = ($school->settings ?? [])['branding'] ?? [];

        return response()->json($this->resolve($schoolBranding, $system, $school));
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

        // The school whose record these details belong to — needed up front so the
        // code's uniqueness rule can ignore that school's own current code.
        $targetSchoolId = $user->hasRole('superadmin') && $request->filled('school_id')
            ? (int) $request->school_id
            : $user->school_id;

        $validated = $request->validate([
            'school_id' => 'sometimes|integer|exists:schools,id',
            'app_name' => 'sometimes|string|max:80',
            'app_tagline' => 'sometimes|string|max:80',
            'logo' => 'sometimes|file|mimes:jpg,jpeg,png,svg,webp|max:2048',

            // School contact details. These live on the schools table rather than in
            // the branding JSON because they are real record fields, not display
            // preferences — receipts, statements and SMS all read them.
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',

            // The short code is the middle segment of every admission number this
            // school issues (SEC/MGRTHMR/0001/2026), so it must stay unique and
            // stripped of anything that would corrupt that format.
            'code' => [
                'sometimes',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('schools', 'code')->ignore($targetSchoolId),
            ],
            // Letterhead block printed on receipts, statements and reports.
            // Stored in settings.letterhead rather than as columns — these are
            // presentation lines that vary per school and are only ever printed.
            'phone_2' => 'sometimes|nullable|string|max:30',
            'phone_3' => 'sometimes|nullable|string|max:30',
            'po_box' => 'sometimes|nullable|string|max:60',
            'address_line1' => 'sometimes|nullable|string|max:80',
            'address_line2' => 'sometimes|nullable|string|max:80',
            'city_country' => 'sometimes|nullable|string|max:80',
            'website' => 'sometimes|nullable|string|max:120',
            'motto' => 'sometimes|nullable|string|max:120',
        ], [
            'code.regex' => 'School code may contain only letters and numbers.',
            'code.unique' => 'That school code is already used by another school.',
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

        // Letterhead lines live in settings, keyed individually so an unsent field
        // keeps its existing value rather than being wiped by a partial save.
        $letterhead = $settings['letterhead'] ?? [];
        foreach (['phone_2', 'phone_3', 'po_box', 'address_line1', 'address_line2',
            'city_country', 'website', 'motto'] as $field) {
            if (array_key_exists($field, $validated)) {
                $letterhead[$field] = $validated[$field] ?: null;
            }
        }
        $settings['letterhead'] = $letterhead;

        // Contact details and the short code are columns on the school itself, not
        // branding preferences — receipts, statements, SMS and admission numbers
        // read them from there.
        $columns = ['settings' => $settings];
        foreach (['phone', 'email'] as $field) {
            if (array_key_exists($field, $validated)) {
                $columns[$field] = $validated[$field] ?: null;
            }
        }
        if (array_key_exists('code', $validated)) {
            $columns['code'] = strtoupper($validated['code']);
        }

        $school->update($columns);

        $system = SystemSetting::get('branding', []);
        $response = array_merge(
            $this->resolve($branding, $system, $school),
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
     *
     * $school is passed when the branding belongs to a specific school, so the
     * response can also carry that school's own record fields (phone, email,
     * code) which the settings form edits alongside the branding.
     */
    private function resolve(array $specific, ?array $system, ?School $school = null): array
    {
        $system = $system ?? [];
        $schoolName = $school?->name;

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

            // Null when resolving system-wide defaults, where there is no school.
            'phone' => $school?->phone,
            'email' => $school?->email,
            'code' => $school?->code,
            'level' => $school?->level?->value,

            // Letterhead lines, flattened so the settings form binds to them directly.
            ...collect(['phone_2', 'phone_3', 'po_box', 'address_line1', 'address_line2',
                'city_country', 'website', 'motto'])
                ->mapWithKeys(fn ($k) => [$k => ($school?->settings['letterhead'][$k] ?? null)])
                ->all(),
        ];
    }
}
