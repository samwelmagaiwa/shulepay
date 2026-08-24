<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TermController extends Controller
{
    /**
     * School ids the current user may touch.
     * Returns null when the user is unrestricted (superadmin).
     */
    private function accessibleSchoolIds(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        return $user->allAccessibleSchoolIds();
    }

    /** Abort unless the given academic year belongs to a school the user may touch. */
    private function authorizeYear(Request $request, ?AcademicYear $year): void
    {
        $allowed = $this->accessibleSchoolIds($request);

        if ($allowed === null) {
            return;
        }

        if (! $year || ! in_array((int) $year->school_id, $allowed, true)) {
            abort(403, 'This term belongs to another school.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Term::query()->with('academicYear');

        // Tenant scope — never leak terms from schools the user cannot access.
        $allowed = $this->accessibleSchoolIds($request);
        if ($allowed !== null) {
            $query->whereHas('academicYear', fn ($q) => $q->whereIn('school_id', $allowed));
        }

        // Optional: all terms for one school, in a single request.
        if ($request->filled('school_id')) {
            $schoolId = (int) $request->query('school_id');

            if ($allowed !== null && ! in_array($schoolId, $allowed, true)) {
                abort(403, 'This school is not accessible.');
            }

            $query->whereHas('academicYear', fn ($q) => $q->where('school_id', $schoolId));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        // Newest year first, then term order within the year.
        $terms = $query
            ->join('academic_years', 'terms.academic_year_id', '=', 'academic_years.id')
            ->orderByDesc('academic_years.name')
            ->orderBy('terms.number')
            ->select('terms.*')
            ->get();

        return response()->json($terms);
    }

    public function show(Request $request, Term $term): JsonResponse
    {
        $this->authorizeYear($request, $term->academicYear);

        return response()->json($term->load('academicYear'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'name' => 'required|string|max:100',
            'number' => [
                'required', 'integer', 'between:1,4',
                Rule::unique('terms')->where('academic_year_id', $request->input('academic_year_id')),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ], [
            'number.unique' => 'This academic year already has a term with that number.',
        ]);

        $year = AcademicYear::find($data['academic_year_id']);
        $this->authorizeYear($request, $year);

        if (! empty($data['is_current'])) {
            Term::where('academic_year_id', $data['academic_year_id'])
                ->update(['is_current' => false]);
        }

        $term = Term::create($data);

        return response()->json($term->load('academicYear'), 201);
    }

    public function update(Request $request, Term $term): JsonResponse
    {
        $this->authorizeYear($request, $term->academicYear);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'number' => [
                'sometimes', 'required', 'integer', 'between:1,4',
                Rule::unique('terms')
                    ->where('academic_year_id', $term->academic_year_id)
                    ->ignore($term->id),
            ],
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_current' => 'boolean',
        ], [
            'number.unique' => 'This academic year already has a term with that number.',
        ]);

        if (! empty($data['is_current'])) {
            Term::where('academic_year_id', $term->academic_year_id)
                ->where('id', '!=', $term->id)
                ->update(['is_current' => false]);
        }

        $term->update($data);

        return response()->json($term->fresh()->load('academicYear'));
    }

    public function destroy(Request $request, Term $term): JsonResponse
    {
        $this->authorizeYear($request, $term->academicYear);

        // Invoices and related records are intentionally preserved (their term_id is nulled).
        $term->delete();

        return response()->json(null, 204);
    }
}
