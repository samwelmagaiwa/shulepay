<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\StudentDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDraftController extends Controller
{
    private function userSchoolIds(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        return $user->allAccessibleSchoolIds();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = StudentDraft::where('user_id', $user->id);

        $allowed = $this->userSchoolIds($request);
        if ($allowed !== null) {
            $query->whereIn('school_id', $allowed);
        }

        if ($request->filled('school_id')) {
            $schoolId = (int) $request->query('school_id');
            if ($allowed !== null && ! in_array($schoolId, $allowed, true)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            $query->where('school_id', $schoolId);
        }

        $drafts = $query->orderByDesc('last_accessed_at')->get();

        return response()->json(['data' => $drafts]);
    }

    public function show(Request $request, StudentDraft $draft): JsonResponse
    {
        $user = $request->user();
        if (! $user || $draft->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $allowed = $this->userSchoolIds($request);
        if ($allowed !== null && ! in_array($draft->school_id, $allowed, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $draft->update(['last_accessed_at' => now()]);

        return response()->json(['data' => $draft]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $schoolId = (int) $request->input('school_id');
        $allowed = $this->userSchoolIds($request);
        if ($allowed !== null && ! in_array($schoolId, $allowed, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rules = [
            'school_id' => 'required|integer|exists:schools,id',
            'current_step' => 'nullable|integer|between:1,6',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'birth_certificate_no' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'religion' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'term_id' => 'nullable|integer|exists:terms,id',
            'enrollment_date' => 'nullable|date',
            'previous_school' => 'nullable|string|max:200',
            'status' => 'nullable|in:active,transferred,graduated,dropped,sponsored,orphaned',
            'notes' => 'nullable|string',
            'identifications' => 'nullable|array',
            'guardians' => 'nullable|array',
            'total_tuition_fee_cents' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:sibling,staff,sponsor,other',
            'discount_amount_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
            'generate_first_invoice' => 'nullable|boolean',
            'is_existing_student' => 'nullable|boolean',
            'migration_mode' => 'nullable|in:detailed,lumpsum',
            'payment_history' => 'nullable|array',
            'lumpsum_total_charged_cents' => 'nullable|integer|min:0',
            'lumpsum_total_paid_cents' => 'nullable|integer|min:0',
        ];

        $validated = $request->validate($rules);

        $validated['user_id'] = $user->id;
        $validated['last_accessed_at'] = now();

        $draft = StudentDraft::updateOrCreate(
            ['user_id' => $user->id, 'school_id' => $schoolId],
            $validated
        );

        return response()->json(['data' => $draft], 201);
    }

    public function update(Request $request, StudentDraft $draft): JsonResponse
    {
        $user = $request->user();
        if (! $user || $draft->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $allowed = $this->userSchoolIds($request);
        if ($allowed !== null && ! in_array($draft->school_id, $allowed, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rules = [
            'current_step' => 'nullable|integer|between:1,6',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date',
            'birth_certificate_no' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:10',
            'allergies' => 'nullable|string|max:255',
            'medical_conditions' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'religion' => 'nullable|string|max:50',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'ward' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:100',
            'school_class_id' => 'nullable|integer|exists:school_classes,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'term_id' => 'nullable|integer|exists:terms,id',
            'enrollment_date' => 'nullable|date',
            'previous_school' => 'nullable|string|max:200',
            'status' => 'nullable|in:active,transferred,graduated,dropped,sponsored,orphaned',
            'notes' => 'nullable|string',
            'identifications' => 'nullable|array',
            'guardians' => 'nullable|array',
            'total_tuition_fee_cents' => 'nullable|integer|min:0',
            'discount_type' => 'nullable|in:sibling,staff,sponsor,other',
            'discount_amount_cents' => 'nullable|integer|min:0',
            'opening_balance_cents' => 'nullable|integer|min:0',
            'generate_first_invoice' => 'nullable|boolean',
            'is_existing_student' => 'nullable|boolean',
            'migration_mode' => 'nullable|in:detailed,lumpsum',
            'payment_history' => 'nullable|array',
            'lumpsum_total_charged_cents' => 'nullable|integer|min:0',
            'lumpsum_total_paid_cents' => 'nullable|integer|min:0',
        ];

        $validated = $request->validate($rules);
        $validated['last_accessed_at'] = now();
        $draft->update($validated);

        return response()->json(['data' => $draft]);
    }

    public function destroy(Request $request, StudentDraft $draft): JsonResponse
    {
        $user = $request->user();
        if (! $user || $draft->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $allowed = $this->userSchoolIds($request);
        if ($allowed !== null && ! in_array($draft->school_id, $allowed, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $draft->delete();

        return response()->json(null, 204);
    }
}
