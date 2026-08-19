<?php
namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Term::query();

        $user = $request->user();
        if ($user && $user->school_id) {
            $query->whereHas('academicYear', function ($q) use ($user) {
                $q->where('school_id', $user->school_id);
            });
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        } else {
            $query->whereHas('academicYear', function ($q) {
                $q->where('is_current', true);
            });
        }

        $terms = $query->orderBy('number')->get();

        // If globally fetched across multiple active academic years, prevent duplicate names in dropdowns
        if (!$request->filled('academic_year_id') && (!$user || !$user->school_id)) {
            $terms = $terms->unique('number')->values();
        }

        return response()->json($terms);
    }

    public function show(Term $term): JsonResponse
    {
        return response()->json($term);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'name'             => 'required|string|max:100',
            'number'           => 'required|integer|between:1,4',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'is_current'       => 'boolean',
        ]);

        if (!empty($data['is_current'])) {
            Term::where('academic_year_id', $data['academic_year_id'])
                ->update(['is_current' => false]);
        }

        $term = Term::create($data);

        return response()->json($term->load('academicYear'), 201);
    }

    public function update(Request $request, Term $term): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:100',
            'number'     => 'sometimes|required|integer|between:1,4',
            'start_date' => 'sometimes|required|date',
            'end_date'   => 'sometimes|required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        if (!empty($data['is_current'])) {
            Term::where('academic_year_id', $term->academic_year_id)
                ->where('id', '!=', $term->id)
                ->update(['is_current' => false]);
        }

        $term->update($data);

        return response()->json($term->load('academicYear'));
    }

    public function destroy(Term $term): JsonResponse
    {
        if ($term->invoices()->exists()) {
            return response()->json(['message' => 'Cannot delete a term that has invoices.'], 422);
        }

        $term->delete();

        return response()->json(null, 204);
    }
}
