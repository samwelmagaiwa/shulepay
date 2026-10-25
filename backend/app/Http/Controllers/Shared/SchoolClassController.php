<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Support\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SchoolClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SchoolClass::query();

        if ($request->filled('school_id')) {
            SchoolScope::authorize($request->user(), (int) $request->school_id);
            $query->where('school_id', $request->school_id);
        } elseif (! $request->boolean('all') && auth()->user()->school_id) {
            $query->where('school_id', auth()->user()->school_id);
        }

        $classes = $query->orderBy('sort_order')->get();

        return response()->json($classes);
    }

    public function show(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        SchoolScope::authorize($request->user(), $schoolClass->school_id);

        return response()->json($schoolClass);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'stream' => 'nullable|string|max:50',
            'school_id' => 'required|integer|exists:schools,id',
            'capacity' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer',
        ]);
        SchoolScope::authorize($request->user(), (int) $data['school_id']);
        $this->assertNameFree($data['school_id'], $data['name'], $data['stream'] ?? null);

        $class = SchoolClass::create($data);

        return response()->json($class, 201);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        SchoolScope::authorize($request->user(), $schoolClass->school_id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'stream' => 'sometimes|nullable|string|max:50',
            'school_id' => 'sometimes|required|integer|exists:schools,id',
            'capacity' => 'sometimes|nullable|integer|min:1',
            'sort_order' => 'sometimes|nullable|integer',
        ]);
        if (isset($data['school_id'])) {
            SchoolScope::authorize($request->user(), (int) $data['school_id']);
        }
        $this->assertNameFree(
            $data['school_id'] ?? $schoolClass->school_id,
            $data['name'] ?? $schoolClass->name,
            array_key_exists('stream', $data) ? $data['stream'] : $schoolClass->stream,
            $schoolClass->id,
        );

        $schoolClass->update($data);

        return response()->json($schoolClass, 200);
    }

    public function destroy(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        SchoolScope::authorize($request->user(), $schoolClass->school_id);

        $hasEnrollments = Enrollment::where('school_class_id', $schoolClass->id)->exists();

        if ($hasEnrollments) {
            return response()->json(['message' => __('Cannot delete class with enrolled students.')], 422);
        }

        $schoolClass->delete();

        return response()->json(null, 204);
    }

    /** The database only forbids a repeated name when the stream is filled in, so say it here for both cases. */
    private function assertNameFree(int|string $schoolId, string $name, ?string $stream, ?int $ignoreId = null): void
    {
        $blank = $stream === null || $stream === '';
        $taken = SchoolClass::query()
            ->where('school_id', $schoolId)->where('name', $name)
            ->when($blank, fn ($q) => $q->where(fn ($w) => $w->whereNull('stream')->orWhere('stream', '')), fn ($q) => $q->where('stream', $stream))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('A class with this name already exists.')]);
        }
    }
}
