<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->query('school_id') ?? auth()->user()->school_id;

        $years = AcademicYear::with('terms')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderByDesc('name')
            ->get();

        return response()->json($years);
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return response()->json($academicYear->load('terms'));
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->input('school_id') ?? auth()->user()->school_id;

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years')->where('school_id', $schoolId),
            ],
            'school_id' => 'required|integer|exists:schools,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
        ]);

        $year = DB::transaction(function () use ($data) {
            if (! empty($data['is_current'])) {
                AcademicYear::where('school_id', $data['school_id'])
                    ->update(['is_current' => false]);
            }

            return AcademicYear::create([
                'name' => $data['name'],
                'school_id' => $data['school_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => $data['is_current'] ?? false,
            ]);
        });

        return response()->json($year, 201);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $schoolId = $request->input('school_id') ?? $academicYear->school_id;

        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('academic_years')
                    ->where('school_id', $schoolId)
                    ->ignore($academicYear->id),
            ],
            'school_id' => 'sometimes|required|integer|exists:schools,id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_current' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($data, $academicYear) {
            if (! empty($data['is_current'])) {
                $schoolId = $data['school_id'] ?? $academicYear->school_id;
                AcademicYear::where('school_id', $schoolId)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }

            $academicYear->update($data);
        });

        return response()->json($academicYear->fresh()->load('terms'), 200);
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->terms()->exists()) {
            return response()->json(['message' => 'An academic year with terms cannot be deleted. Please delete its terms first.'], 422);
        }
        $academicYear->delete();

        return response()->json(['message' => 'Academic year deleted.']);
    }
}
