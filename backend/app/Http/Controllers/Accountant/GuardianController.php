<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\User;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // A guardian can have children enrolled at different schools. Scoped
        // to the active school so switching schools shows only that school's
        // children — both which guardians appear and which of their students
        // are listed — matching every other school-switchable list page.
        $schoolId = $this->activeSchoolId($request);

        $guardians = Guardian::with(['user', 'students' => function ($q) use ($schoolId) {
            $q->withPivot(['relation', 'is_primary']);
            if ($schoolId) {
                $q->whereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId));
            }
        }])
            ->when($schoolId, fn ($q) => $q->whereHas('students', fn ($s) => $s->whereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId))
            )
            )
            ->when($request->student_id, fn ($q) => $q->whereHas('students', fn ($s) => $s->where('students.id', $request->student_id))
            )
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($sub) use ($s) {
                    $sub->where(fn ($nameQ) => NameSearch::apply($nameQ, ['first_name', 'last_name'], $s))
                        ->orWhere('phone', 'like', "%{$s}%")
                        ->orWhereHas('user', function ($u) use ($s) {
                            $u->where(fn ($nameQ) => NameSearch::apply($nameQ, ['name'], $s))
                                ->orWhere('email', 'like', "%{$s}%")
                                ->orWhere('phone', 'like', "%{$s}%");
                        });
                });
            })
            ->paginate(20);

        $items = $guardians->getCollection()->map(fn ($g) => [
            'id' => $g->id,
            'full_name' => $g->fullName() ?: ($g->user?->name ?? '—'),
            'phone' => $g->phone ?: $g->user?->phone,
            'email' => $g->email ?: $g->user?->email,
            'relation' => $g->students->first()?->pivot?->relation ?? null,
            'is_primary' => (bool) ($g->students->first()?->pivot?->is_primary ?? false),
            'user' => $g->user ? ['id' => $g->user->id, 'name' => $g->user->name, 'phone' => $g->user->phone] : null,
            'students' => $g->students->map(fn ($s) => [
                'id' => $s->id,
                'full_name' => $s->fullName(),
                'admission_number' => $s->currentEnrollment?->admission_number,
                'pivot_relation' => $s->pivot?->relation,
            ]),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $guardians->currentPage(),
                'last_page' => $guardians->lastPage(),
                'per_page' => $guardians->perPage(),
                'total' => $guardians->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:120',
            'phone' => 'required|string|max:20',
            'relation' => 'nullable|string|max:50',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'password' => bcrypt(Str::random(12)),
            'role' => 'parent',
        ]);

        $user->assignRole('parent');

        $parts = explode(' ', trim($data['name']), 2);
        $guardian = Guardian::create([
            'user_id' => $user->id,
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? $parts[0],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'relation' => $data['relation'] ?? 'mlezi',
        ]);

        $guardian->students()->sync($data['student_ids']);

        return response()->json($guardian->load(['user', 'students']), 201);
    }

    public function show(Guardian $guardian): JsonResponse
    {
        return response()->json($guardian->load(['user', 'students.currentEnrollment.schoolClass']));
    }

    public function update(Request $request, Guardian $guardian): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:120',
            'phone' => 'sometimes|string|max:20',
            'relation' => 'nullable|string|max:50',
            'student_ids' => 'sometimes|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        // Update name/phone on the linked user if one exists
        if ($guardian->user) {
            $guardian->user->update(array_filter([
                'name' => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($v) => ! is_null($v)));
        }

        // Also keep first_name/last_name/phone on the guardian row itself
        $parts = isset($data['name']) ? explode(' ', trim($data['name']), 2) : null;
        $guardian->update(array_filter([
            'first_name' => $parts ? $parts[0] : null,
            'last_name' => $parts ? ($parts[1] ?? $parts[0]) : null,
            'phone' => $data['phone'] ?? null,
        ], fn ($v) => ! is_null($v)));

        if (isset($data['student_ids'])) {
            // The checklist that produced student_ids only ever offers the
            // active school's students (see index()), so a plain sync() here
            // would silently detach every child the guardian has at any
            // OTHER school. Keep those associations and replace only the
            // active school's slice with what was submitted.
            $schoolId = $this->activeSchoolId($request);
            if ($schoolId) {
                $otherSchoolIds = $guardian->students()
                    ->whereDoesntHave('enrollments', fn ($e) => $e->where('school_id', $schoolId))
                    ->pluck('students.id')
                    ->all();
                $guardian->students()->sync(array_values(array_unique(array_merge($otherSchoolIds, $data['student_ids']))));
            } else {
                $guardian->students()->sync($data['student_ids']);
            }
        }

        return response()->json($guardian->load(['user', 'students']));
    }

    public function destroy(Guardian $guardian): JsonResponse
    {
        $guardian->students()->detach();
        $guardian->user?->delete();
        $guardian->delete();

        return response()->json(null, 204);
    }

    private function activeSchoolId(Request $request): ?int
    {
        if ($request->filled('school_id')) {
            return $request->integer('school_id');
        }

        return app()->bound('active_school') ? app('active_school')?->id : null;
    }
}
