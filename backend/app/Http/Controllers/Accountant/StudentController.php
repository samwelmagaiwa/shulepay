<?php
namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RegisterStudentRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Student;
use App\Services\Students\StudentRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{

    public function __construct(private StudentRegistrationService $registrationService)
    {
    }

    public function register(RegisterStudentRequest $request): JsonResponse
    {
        $student = $this->registrationService->register(
            $request->validated(),
            $request->file('photo')
        );
        return response()->json(new StudentResource($student), 201);
    }

    public function nextAdmissionNumber(Request $request): JsonResponse
    {
        $request->validate(['school_id' => 'required|exists:schools,id']);
        $school = School::findOrFail($request->school_id);
        return response()->json(['admission_number' => $school->nextAdmissionNumber()]);
    }
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Student::query();

        if ($request->filled('school_id')) {
            $schoolId = $request->integer('school_id');
            $query->whereHas('enrollments', fn($q) => $q->where('school_id', $schoolId)->where('status', 'active'));
        } elseif (auth()->user()->school_id) {
            $schoolId = auth()->user()->school_id;
            $query->whereHas('enrollments', fn($q) => $q->where('school_id', $schoolId)->where('status', 'active'));
        } else {
            $query->whereHas('enrollments', fn($q) => $q->where('status', 'active'));
        }

        $query->with([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
            'guardians',
        ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(
                fn($q) => $q
                    ->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhereHas('enrollments', fn($eq) => $eq->where('admission_number', 'like', "%{$s}%"))
            );
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('school_class_id')) {
            $query->whereHas('enrollments', fn($q) => $q->where('school_class_id', $request->school_class_id));
        }

        // has_debt=1 → only students with unpaid balance; has_debt=0 → only fully paid
        if ($request->filled('has_debt')) {
            $schoolIdForDebt = $schoolId ?? auth()->user()->school_id;
            if ((int) $request->has_debt === 1) {
                // has at least one invoice where balance > 0
                $query->whereHas('invoices', function ($q) use ($schoolIdForDebt) {
                    $q->where('school_id', $schoolIdForDebt)
                      ->whereIn('status', ['unpaid', 'partial']);
                });
            } else {
                // no unpaid/partial invoices
                $query->whereDoesntHave('invoices', function ($q) use ($schoolIdForDebt) {
                    $q->where('school_id', $schoolIdForDebt)
                      ->whereIn('status', ['unpaid', 'partial']);
                });
            }
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return StudentResource::collection($query->orderBy('last_name')->paginate($perPage));
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $student = Student::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
            ]);

            $school = School::findOrFail($validated['school_id']);
            $admissionNo = $school->nextAdmissionNumber();

            $student->enrollments()->create([
                'school_id' => $validated['school_id'],
                'school_class_id' => $validated['school_class_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'admission_number' => $admissionNo,
                'status' => 'active',
                'admitted_at' => $validated['admitted_at'],
            ]);

            AuditLog::record('student_created', $student, [], $student->toArray());

            return response()->json(
                new StudentResource($student->load(['currentEnrollment.schoolClass', 'currentEnrollment.school'])),
                201
            );
        });
    }

    public function show(Student $student): StudentResource
    {
        $student->load([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
            'enrollments.school',
            'enrollments.schoolClass',
            'guardians',
            'invoices.payments',
            'invoices.term',
        ]);
        return new StudentResource($student);
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $before = $student->toArray();
        $validated = $request->validated();

        // Handle photo update
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('student-photos', 'public');
        }

        $fields = [
            'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'status',
            'birth_certificate_no', 'nationality', 'religion',
            'blood_group', 'allergies', 'medical_conditions',
            'address', 'region', 'district', 'ward', 'street',
            'photo', 'notes',
        ];
        $studentData = array_filter(
            array_intersect_key($validated, array_flip($fields)),
            fn($v) => !is_null($v)
        );

        if (!empty($studentData)) {
            $student->update($studentData);
        }

        // Update current enrollment class if provided
        if (isset($validated['school_class_id'])) {
            $student->currentEnrollment?->update(['school_class_id' => $validated['school_class_id']]);
        }

        AuditLog::record('student_updated', $student, $before, $student->fresh()->toArray());
        return new StudentResource($student->load(['currentEnrollment.schoolClass', 'currentEnrollment.school']));
    }

    public function destroy(Student $student): JsonResponse
    {
        AuditLog::record('student_deleted', $student, $student->toArray(), []);
        $student->delete();
        return response()->json(['message' => 'Mwanafunzi amefutwa.']);
    }
}
