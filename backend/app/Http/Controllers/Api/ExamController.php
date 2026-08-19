<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index()
    {
        return response()->json(Exam::paginate(50));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'term_id' => 'required|exists:terms,id',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        return response()->json(Exam::create($validated), 201);
    }

    public function show(Exam $exam)
    {
        return response()->json($exam);
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'school_id' => 'sometimes|exists:schools,id',
            'academic_year_id' => 'sometimes|exists:academic_years,id',
            'term_id' => 'sometimes|exists:terms,id',
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $exam->update($validated);

        return response()->json($exam);
    }

    public function destroy(Exam $exam)
    {
        $exam->delete();

        return response()->noContent();
    }
}
