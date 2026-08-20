<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SchoolController extends Controller
{
    /** GET /api/schools — returns only schools the authenticated user can access */
    public function index(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = School::withCount('enrollments');

        if (! $request->boolean('all')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $q = '%'.$request->search.'%';
            $query->where(fn ($w) => $w->where('name', 'like', $q)->orWhere('code', 'like', $q));
        }

        // Superadmin sees every school; others are restricted to their accessible set
        if ($user && ! $user->hasRole('superadmin')) {
            $ids = $user->allAccessibleSchoolIds();
            if (! empty($ids)) {
                $query->whereIn('id', $ids);
            } elseif ($user->school_id) {
                // No explicit multi-school grants — only their primary school
                $query->where('id', $user->school_id);
            }
        }

        $schools = $query->orderBy('name')->get();

        return response()->json(SchoolResource::collection($schools));
    }

    /** GET /api/schools/{school} */
    public function show(School $school): JsonResponse
    {
        $school->loadCount('enrollments');

        return response()->json(new SchoolResource($school));
    }

    /** POST /api/schools — owner only */
    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        // Auto-generate registration number if not provided
        if (empty($data['registration_number'])) {
            $data['registration_number'] = $this->generateRegNumber($data['name'], $data['level']);
        }

        // Ensure slug uniqueness
        $base = $data['slug'];
        $count = 1;
        while (School::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base.'-'.$count++;
        }

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $school = School::create($data);

        AuditLogger::log('school.created', $school, $data);

        return response()->json(new SchoolResource($school), 201);
    }

    /** PUT /PATCH /api/schools/{school} — owner only */
    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            // Remove old logo
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        // Re-slug if name changed
        if (isset($data['name']) && $data['name'] !== $school->name) {
            $base = Str::slug($data['name']);
            $slug = $base;
            $count = 1;
            while (School::where('slug', $slug)->where('id', '!=', $school->id)->exists()) {
                $slug = $base.'-'.$count++;
            }
            $data['slug'] = $slug;
        }

        $before = $school->toArray();
        $school->update($data);

        AuditLogger::log('school.updated', $school, ['before' => $before, 'after' => $data]);

        return response()->json(new SchoolResource($school->fresh()->loadCount('enrollments')));
    }

    /** DELETE /api/schools/{school} — owner only; soft-deactivates instead of hard delete */
    public function destroy(School $school): JsonResponse
    {
        // Hard delete only if school has no enrollments
        if ($school->enrollments()->exists()) {
            $school->update(['is_active' => false]);
            AuditLogger::log('school.deactivated', $school, []);

            return response()->json(['message' => 'School deactivated (has existing students).']);
        }

        AuditLogger::log('school.deleted', $school, $school->toArray());
        $school->delete();

        return response()->json(null, 204);
    }

    /**
     * Generate registration number:
     *   Primary:   PRM/{ABBREV}/{SEQ:4}/{YEAR}
     *   Secondary: SEC/{ABBREV}/{SEQ:4}/{YEAR}
     *
     * Abbreviation = remove vowels & stop-words from school name, uppercase, max 8 chars.
     */
    private function generateRegNumber(string $name, string $level): string
    {
        $prefix = strtoupper($level) === 'SECONDARY' ? 'SEC' : 'PRM';

        // Strip stop-words, keep significant words
        $stopWords = ['school', 'primary', 'secondary', 'st', 'saint', 'ya', 'the', 'of', 'and', 'la', 'da'];
        $words = preg_split('/[\s.]+/', strtolower($name));
        $significant = array_filter($words, fn ($w) => $w && ! in_array(rtrim($w, '.'), $stopWords));

        // Remove vowels from each word, uppercase, join
        $abbrev = implode('', array_map(fn ($w) => preg_replace('/[aeiou]/i', '', $w), $significant));
        $abbrev = strtoupper(substr($abbrev, 0, 8));
        if (! $abbrev) {
            $abbrev = strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 6));
        }

        // Sequential number among schools with same prefix
        $year = now()->year;
        $seq = School::where('registration_number', 'like', "{$prefix}/%/{$year}")->count() + 1;

        return sprintf('%s/%s/%04d/%d', $prefix, $abbrev, $seq, $year);
    }

    /** PATCH /api/schools/{school}/toggle-status — owner only */
    public function toggleStatus(School $school): JsonResponse
    {
        $school->update(['is_active' => ! $school->is_active]);
        AuditLogger::log('school.status_toggled', $school, ['is_active' => $school->is_active]);

        return response()->json(new SchoolResource($school));
    }
}
