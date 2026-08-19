<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use App\Services\Billing\InvoiceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceGenerator $generator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // BelongsToSchool global scope handles school_id filtering automatically.
        // "Shule Zote" mode: SetActiveSchool leaves active_school unbound → no filter.
        $query = Invoice::with(['student.currentEnrollment.schoolClass', 'term', 'academicYear', 'lines', 'payments']);

        if ($request->filled('school_id') && (int) $request->school_id !== 0) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('term_number')) {
            $query->whereHas('term', fn ($q) => $q->where('number', (int) $request->term_number));
        } elseif ($request->filled('term_id')) {
            $query->where('term_id', $request->integer('term_id'));
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', $request->invoice_number);
        }
        if ($request->filled('school_class_id')) {
            $query->whereHas(
                'student.currentEnrollment',
                fn ($q) => $q->where('school_class_id', $request->school_class_id)
            );
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                    ->orWhereHas('student', fn ($sq) => $sq
                        ->where('first_name', 'like', "%{$s}%")
                        ->orWhere('middle_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                    )
                    ->orWhereHas('student.currentEnrollment', fn ($sq) => $sq
                        ->where('admission_number', 'like', "%{$s}%")
                    );
            });
        }

        if ($request->filled('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->status)));
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        }

        return InvoiceResource::collection($query->latest()->paginate($request->input('per_page', 20)));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['student.currentEnrollment', 'term', 'lines', 'payments.receipt']));
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'term_id' => 'required|exists:terms,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'student_id' => 'nullable|exists:students,id',
        ]);

        $term = Term::findOrFail($request->term_id);
        $year = AcademicYear::findOrFail($request->academic_year_id);

        if ($request->filled('student_id')) {
            $student = Student::findOrFail($request->student_id);
            $invoice = $this->generator->generateForStudent($student, $term, $year);

            return response()->json(new InvoiceResource($invoice), 201);
        }

        $results = $this->generator->generateForTerm($term, $year);

        return response()->json($results, 201);
    }
}
