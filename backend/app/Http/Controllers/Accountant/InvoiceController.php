<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use App\Services\Billing\InvoiceGenerator;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceGenerator $generator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // BelongsToSchool global scope handles school_id filtering automatically.
        // "Shule Zote" mode: SetActiveSchool leaves active_school unbound → no filter.
        // payments.receipt is needed so the list can offer a "Print Receipt" action
        // without a follow-up request per row.
        $query = Invoice::with([
            'student.currentEnrollment.schoolClass', 'term', 'academicYear',
            'lines', 'payments.receipt',
        ]);

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
                    ->orWhereHas('student', fn ($sq) => NameSearch::apply(
                        $sq, ['first_name', 'middle_name', 'last_name'], $s
                    ))
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

        $perPage = (int) $request->input('per_page', 20);

        // The list collapses a student's invoices into one row, so it must paginate
        // by STUDENT. Paginating invoices meant 20 rows' worth of data rendered as
        // ~5 rows (four terms each), leaving the page half empty and the "showing
        // 1-20 of N" counter describing something other than what was on screen.
        // It also split a student across a page boundary whenever their invoices
        // straddled one.
        if ($request->boolean('group_by_student')) {
            $studentPage = (clone $query)->toBase()
                ->select('invoices.student_id')
                ->selectRaw('MAX(invoices.id) as latest_invoice_id')
                ->groupBy('invoices.student_id')
                ->orderByDesc('latest_invoice_id')
                ->paginate($perPage, ['*'], 'page', $request->input('page'));

            $studentIds = collect($studentPage->items())->pluck('student_id')->all();

            $invoices = $studentIds
                ? $query->whereIn('student_id', $studentIds)
                    ->orderByDesc('created_at')->orderByDesc('id')->get()
                : collect();

            // Page meta counts students (what the user sees as rows); the payload is
            // every invoice belonging to them, so no group is ever cut in half.
            return InvoiceResource::collection(
                new LengthAwarePaginator(
                    $invoices,
                    $studentPage->total(),
                    $studentPage->perPage(),
                    $studentPage->currentPage(),
                    ['path' => $request->url(), 'query' => $request->query()]
                )
            );
        }

        // created_at alone is not unique — a migration import writes every term's invoice
        // in one transaction, so they share a timestamp. Without a unique tiebreaker MySQL
        // may order those ties differently for each page, letting a row appear twice or
        // vanish across a pagination boundary. id breaks the tie deterministically.
        return InvoiceResource::collection(
            $query->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['student.currentEnrollment', 'term', 'lines', 'payments.receipt']));
    }

    public function generatePreview(Request $request): JsonResponse
    {
        $request->validate([
            'term_id' => 'required|exists:terms,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'student_id' => 'nullable|exists:students,id',
        ]);

        $term = Term::findOrFail($request->term_id);
        $year = AcademicYear::findOrFail($request->academic_year_id);

        if ($request->filled('student_id')) {
            $count = 1;
        } else {
            $count = Enrollment::where('school_id', $year->school_id)
                ->where('status', 'active')
                ->count();
        }

        return response()->json([
            'count' => $count,
            'term' => $term->name,
            'academic_year' => $year->name ?? $year->year,
        ]);
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
