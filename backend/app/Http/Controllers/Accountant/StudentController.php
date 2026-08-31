<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RegisterStudentRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentFullRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Services\Students\StudentRegistrationService;
use App\Services\Students\StudentUpdateService;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function __construct(
        private StudentRegistrationService $registrationService,
        private StudentUpdateService $updateService
    ) {}

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

        $user = auth()->user();
        // Superadmin, owner, and accountant may switch school via request param or X-School-Id header
        $requestedSchoolId = $request->filled('school_id')
            ? $request->integer('school_id')
            : ((int) $request->header('X-School-Id') ?: null);

        if ($user->hasRole('superadmin') || $user->hasRole('owner') || $user->hasRole('accountant')) {
            $schoolId = $requestedSchoolId ?? $user->school_id;
        } else {
            $schoolId = $user->school_id;
        }

        if ($schoolId) {
            $query->whereHas('enrollments', fn ($q) => $q->where('school_id', $schoolId));
        } else {
            $query->whereHas('enrollments');
        }

        $query->with([
            'currentEnrollment.schoolClass',
            'currentEnrollment.school',
            'guardians',
        ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where(fn ($nameQ) => NameSearch::apply($nameQ, ['first_name', 'middle_name', 'last_name'], $s))
                    ->orWhereHas('enrollments', fn ($eq) => $eq->where('admission_number', 'like', "%{$s}%"))
                    ->orWhereHas('invoices', fn ($iq) => $iq->where('invoice_number', 'like', "%{$s}%"));
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('sponsorship_type')) {
            $query->where('sponsorship_type', $request->sponsorship_type);
        }
        if ($request->filled('school_class_id')) {
            $query->whereHas('enrollments', fn ($q) => $q->where('school_class_id', $request->school_class_id));
        }

        // has_debt=1 → unpaid or partial; has_debt=partial → partial only; has_debt=0 → fully paid
        if ($request->filled('has_debt')) {
            $schoolIdForDebt = $schoolId ?? auth()->user()->school_id;
            if ($request->has_debt === 'partial') {
                // has at least one invoice that's been started but not fully paid off
                $query->whereHas('invoices', function ($q) use ($schoolIdForDebt) {
                    $q->where('school_id', $schoolIdForDebt)->where('status', 'partial');
                });
            } elseif ((int) $request->has_debt === 1) {
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
        $this->authorizeStudentAccess($student);

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
        $this->authorizeStudentAccess($student);

        $before = $student->toArray();
        $validated = $request->validated();

        // Handle photo update
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('student-photos', 'public');
        }

        $fields = [
            'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'status',
            'sponsorship_type',
            'birth_certificate_no', 'nationality', 'religion',
            'blood_group', 'allergies', 'medical_conditions',
            'address', 'region', 'district', 'ward', 'street', 'place',
            'photo', 'notes',
        ];
        $studentData = array_filter(
            array_intersect_key($validated, array_flip($fields)),
            fn ($v) => ! is_null($v)
        );

        if (array_key_exists('sponsorship_type', $studentData) || array_key_exists('status', $studentData)) {
            $studentData['status'] = Student::effectiveStatus(
                $studentData['sponsorship_type'] ?? $student->sponsorship_type,
                $studentData['status'] ?? $student->status
            );
        }

        if (! empty($studentData)) {
            $student->update($studentData);
        }

        // Update current enrollment class if provided
        if (isset($validated['school_class_id'])) {
            $student->currentEnrollment?->update(['school_class_id' => $validated['school_class_id']]);
        }

        AuditLog::record('student_updated', $student, $before, $student->fresh()->toArray());

        return new StudentResource($student->load(['currentEnrollment.schoolClass', 'currentEnrollment.school']));
    }

    public function updateFull(UpdateStudentFullRequest $request, Student $student): StudentResource
    {
        $this->authorizeStudentAccess($student);

        $student = $this->updateService->update(
            $student,
            $request->validated(),
            $request->file('photo')
        );

        return new StudentResource($student);
    }

    /**
     * Per-class breakdown of students who currently have a discount, for the
     * "Students with Discount" dashboard card (formerly "Absent Today").
     * Mirrors AttendanceController::summary's shape/scoping so the widget's
     * existing dropdown UI could be repointed here directly.
     */
    public function discountedByClass(Request $request): JsonResponse
    {
        $schoolId = app()->bound('active_school') ? app('active_school')->id : auth()->user()->school_id;

        $summary = Discount::query()
            ->join('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'discounts.student_id')
                    ->where('enrollments.status', 'active');
            })
            ->join('school_classes', 'school_classes.id', '=', 'enrollments.school_class_id')
            ->when($schoolId, fn ($q) => $q->where('enrollments.school_id', $schoolId))
            ->select(
                'school_classes.id as class_id',
                'school_classes.name as class_name',
                DB::raw('count(distinct discounts.student_id) as discounted')
            )
            ->groupBy('school_classes.id', 'school_classes.name')
            ->get();

        return response()->json($summary);
    }

    /**
     * Students registered more than once.
     *
     * Read-only on purpose: which of a pair is authoritative, and whether the
     * money was received twice or merely recorded twice, is a judgement about
     * the school's books that no automatic rule should make.
     *
     * Grouped on first name + last name + date of birth. Name alone would list
     * every namesake; adding the birth date makes a hit nearly always the same
     * child, which is the same test registration now refuses on.
     */
    public function duplicates(Request $request): JsonResponse
    {
        // Student carries no school_id of its own (identity-only — see the
        // model), so scoping to the active school means filtering by
        // enrollment. Left null in "Shule Zote" mode (no active_school
        // bound), which keeps today's system-wide behavior there — matching
        // how Invoice/Payment's own BelongsToSchool scope already behaves
        // everywhere else in the app.
        $schoolId = app()->bound('active_school') ? app('active_school')?->id : null;

        $keys = Student::query()
            ->selectRaw('LOWER(TRIM(first_name)) as fn, LOWER(TRIM(last_name)) as ln, date_of_birth, COUNT(*) as n')
            ->whereNotNull('date_of_birth')
            ->when($schoolId, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId)))
            ->groupBy('fn', 'ln', 'date_of_birth')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $groups = $keys->map(function ($key) use ($schoolId) {
            $students = Student::query()
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$key->fn])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$key->ln])
                ->whereDate('date_of_birth', $key->date_of_birth)
                ->when($schoolId, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('school_id', $schoolId)))
                // Invoice's own BelongsToSchool scope already restricts to the
                // active school automatically — no need to strip it here.
                ->with(['currentEnrollment.schoolClass', 'invoices'])
                ->orderBy('id')
                ->get();

            $rows = $students->map(function ($student) {
                $invoices = $student->invoices;
                $paid = Payment::where('student_id', $student->id)->sum('amount_cents');

                return [
                    'id' => $student->id,
                    'full_name' => $student->fullName(),
                    'date_of_birth' => $student->date_of_birth?->toDateString(),
                    'admission_number' => $student->currentEnrollment?->admission_number,
                    'class' => $student->currentEnrollment?->schoolClass?->name,
                    'invoice_count' => $invoices->count(),
                    'billed_cents' => (int) $invoices->sum(fn ($i) => $i->total_amount_cents->cents()),
                    'paid_cents' => (int) $paid,
                    'registered_at' => $student->created_at?->toDateTimeString(),
                ];
            });

            // Two records billed and paid the same almost always means one
            // registration entered twice, rather than two children who happen to
            // share a name and a birthday. Flagging it saves reading the numbers.
            $identical = $rows->pluck('billed_cents')->unique()->count() === 1
                && $rows->pluck('paid_cents')->unique()->count() === 1;

            return [
                'name' => $rows->first()['full_name'],
                'date_of_birth' => $rows->first()['date_of_birth'],
                'count' => $rows->count(),
                'identical_amounts' => $identical,
                'duplicated_paid_cents' => $identical
                    ? (int) $rows->first()['paid_cents'] * ($rows->count() - 1)
                    : 0,
                'students' => $rows->values(),
            ];
        })
            // Worst first: the ones where money is duplicated matter most.
            ->sortByDesc('duplicated_paid_cents')
            ->values();

        return response()->json([
            'groups' => $groups,
            'group_count' => $groups->count(),
            'student_count' => $groups->sum('count'),
            'duplicated_paid_cents' => $groups->sum('duplicated_paid_cents'),
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($student);

        DB::transaction(function () use ($student) {
            AuditLog::record('student_deleted', $student, $student->toArray(), []);

            // Soft-deleting the student alone does not cascade to enrollments — the FK's
            // cascadeOnDelete only fires on a real SQL DELETE, never on a soft delete.
            // Left untouched, the enrollment stays status='active' forever, so every count
            // based on active enrollments (dashboard "All Students", etc.) keeps including
            // a student that was supposedly deleted. Mark enrollments dropped so deletion
            // actually takes effect everywhere, not just in the students list.
            $student->enrollments()->where('status', 'active')->update(['status' => 'dropped']);

            // Invoices and payments are deliberately LEFT IN PLACE.
            //
            // This used to hard-delete every invoice and force-delete every
            // payment in the same transaction, which destroyed the record that
            // money had ever been collected — irreversibly, and with nothing in
            // the confirm dialog to say so. Deleting a student is a roll-call
            // correction; it should not erase the school's financial history.
            //
            // The invoices become "orphaned" (their student is soft-deleted) and
            // are listed by InvoiceController::orphaned() so they can be cleared
            // deliberately, once someone has seen what they are worth.
            $student->delete();
        });

        return response()->json(['message' => 'Student deleted.']);
    }

    /**
     * What deleting this student would leave behind.
     *
     * Shown in the confirm dialog: the totals here are the difference between a
     * routine correction and wiping out a term's collections, and the person
     * clicking Delete is the only one who can tell which it is.
     */
    public function deletionPreview(Student $student): JsonResponse
    {
        $this->authorizeStudentAccess($student);

        $invoices = $student->invoices()
            ->withoutGlobalScope('school')
            ->with(['term:id,name', 'payments'])
            ->orderBy('id')
            ->get();

        $paidCents = 0;
        $paymentCount = 0;

        $rows = $invoices->map(function ($invoice) use (&$paidCents, &$paymentCount) {
            $paid = $invoice->paidCents();
            $paidCents += $paid;
            $paymentCount += $invoice->payments->count();

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'term' => $invoice->term->name ?? '—',
                'total_cents' => $invoice->total_amount_cents->cents(),
                'paid_cents' => $paid,
                'status' => $invoice->status,
            ];
        });

        return response()->json([
            'student' => $student->fullName(),
            'invoices' => $rows,
            'invoice_count' => $rows->count(),
            'total_billed_cents' => $rows->sum('total_cents'),
            'payment_count' => $paymentCount,
            'total_paid_cents' => $paidCents,
        ]);
    }

    /**
     * Student carries no school_id of its own (identity-only — a student can
     * have enrollments at more than one school over time), so route-model
     * binding alone lets ANY authenticated finance/teaching-staff user view,
     * edit, or delete another school's student purely by guessing/incrementing
     * the numeric ID — there was no ownership check anywhere on this
     * controller. Access is granted if the user can reach at least one school
     * this student has ever been enrolled at.
     */
    private function authorizeStudentAccess(Student $student): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }

        $schoolIds = $student->enrollments()->pluck('school_id')->unique();
        abort_unless(
            $schoolIds->contains(fn ($id) => $user->canAccessSchool((int) $id)),
            403,
            'You do not have access to this student.'
        );
    }
}
