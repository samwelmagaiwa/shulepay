<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        return response()->json(Subject::paginate(50));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        return response()->json(Subject::create($validated), 201);
    }

    public function show(Subject $subject)
    {
        return response()->json($subject);
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'school_id' => 'sometimes|exists:schools,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $subject->update($validated);

        return response()->json($subject);
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return response()->noContent();
    }
}
