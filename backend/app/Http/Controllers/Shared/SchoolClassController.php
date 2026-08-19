<?php
namespace App\Http\Controllers\Shared;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller {
    public function index(Request $request): JsonResponse {
        $query = SchoolClass::query();

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        } elseif (!$request->boolean('all') && auth()->user()->school_id) {
            $query->where('school_id', auth()->user()->school_id);
        }

        $classes = $query->orderBy('sort_order')->get();
        return response()->json($classes);
    }

    public function show(SchoolClass $schoolClass): JsonResponse {
        return response()->json($schoolClass);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'school_id'  => 'required|integer|exists:schools,id',
            'capacity'   => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);

        $class = SchoolClass::create($data);

        return response()->json($class, 201);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse {
        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:100',
            'school_id'  => 'sometimes|required|integer|exists:schools,id',
            'capacity'   => 'sometimes|nullable|integer|min:1',
            'sort_order' => 'sometimes|nullable|integer',
        ]);

        $schoolClass->update($data);

        return response()->json($schoolClass, 200);
    }

    public function destroy(SchoolClass $schoolClass): JsonResponse {
        $hasEnrollments = Enrollment::where('school_class_id', $schoolClass->id)->exists();

        if ($hasEnrollments) {
            return response()->json(['message' => 'Cannot delete class with enrolled students.'], 422);
        }

        $schoolClass->delete();

        return response()->json(null, 204);
    }
}
