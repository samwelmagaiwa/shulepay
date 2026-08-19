<?php
namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guardians = Guardian::with(['user', 'students' => fn($q) => $q->withPivot(['relation', 'is_primary'])])
            ->when($request->student_id, fn($q) =>
                $q->whereHas('students', fn($s) => $s->where('students.id', $request->student_id))
            )
            ->when($request->search, fn($q) =>
                $q->where(fn($sub) =>
                    $sub->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhereHas('user', fn($u) =>
                            $u->where('name', 'like', "%{$request->search}%")
                              ->orWhere('email', 'like', "%{$request->search}%")
                              ->orWhere('phone', 'like', "%{$request->search}%")
                        )
                )
            )
            ->paginate(20);

        $items = $guardians->getCollection()->map(fn($g) => [
            'id'        => $g->id,
            'full_name' => $g->fullName() ?: ($g->user?->name ?? '—'),
            'phone'     => $g->phone ?: $g->user?->phone,
            'email'     => $g->email ?: $g->user?->email,
            'relation'  => $g->students->first()?->pivot?->relation ?? null,
            'is_primary'=> (bool) ($g->students->first()?->pivot?->is_primary ?? false),
            'user'      => $g->user ? ['id' => $g->user->id, 'name' => $g->user->name, 'phone' => $g->user->phone] : null,
            'students'  => $g->students->map(fn($s) => [
                'id'               => $s->id,
                'full_name'        => $s->fullName(),
                'admission_number' => $s->currentEnrollment?->admission_number,
                'pivot_relation'   => $s->pivot?->relation,
            ]),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $guardians->currentPage(),
                'last_page'    => $guardians->lastPage(),
                'per_page'     => $guardians->perPage(),
                'total'        => $guardians->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'email'      => 'nullable|email|max:120',
            'phone'      => 'required|string|max:20',
            'relation'   => 'nullable|string|max:50',
            'student_ids'=> 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $user = \App\Models\User::create([
            'name'     => $data['name'],
            'email'    => $data['email'] ?? null,
            'phone'    => $data['phone'],
            'password' => bcrypt(\Illuminate\Support\Str::random(12)),
            'role'     => 'parent',
        ]);

        $user->assignRole('parent');

        $parts = explode(' ', trim($data['name']), 2);
        $guardian = Guardian::create([
            'user_id'    => $user->id,
            'first_name' => $parts[0],
            'last_name'  => $parts[1] ?? $parts[0],
            'phone'      => $data['phone'],
            'email'      => $data['email'] ?? null,
            'relation'   => $data['relation'] ?? 'mlezi',
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
            'name'       => 'sometimes|string|max:120',
            'phone'      => 'sometimes|string|max:20',
            'relation'   => 'nullable|string|max:50',
            'student_ids'=> 'sometimes|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        // Update name/phone on the linked user if one exists
        if ($guardian->user) {
            $guardian->user->update(array_filter([
                'name'  => $data['name']  ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn($v) => !is_null($v)));
        }

        // Also keep first_name/last_name/phone on the guardian row itself
        $parts = isset($data['name']) ? explode(' ', trim($data['name']), 2) : null;
        $guardian->update(array_filter([
            'first_name' => $parts ? $parts[0] : null,
            'last_name'  => $parts ? ($parts[1] ?? $parts[0]) : null,
            'phone'      => $data['phone'] ?? null,
        ], fn($v) => !is_null($v)));

        if (isset($data['student_ids'])) {
            $guardian->students()->sync($data['student_ids']);
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
}
