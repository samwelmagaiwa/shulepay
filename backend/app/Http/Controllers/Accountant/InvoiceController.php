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
use Illuminate\Support\Facades\DB;

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

    /**
     * Invoices whose student has been deleted.
     *
     * Deleting a student no longer destroys their invoices (see
     * StudentController::destroy), so they collect here instead of vanishing.
     * Listing them with what was billed and collected is the point: an invoice
     * with payments against it is a financial record, and clearing it should be
     * a decision, not a side effect.
     */
    public function orphaned(Request $request): JsonResponse
    {
        $invoices = Invoice::withoutGlobalScope('school')
            ->whereHas('student', fn ($q) => $q->onlyTrashed())
            ->when(
                $request->filled('school_id') && (int) $request->school_id !== 0,
                fn ($q) => $q->where('school_id', $request->school_id)
            )
            ->with(['term:id,name', 'payments'])
            // student() hides soft-deleted rows, so the name has to be fetched
            // through a relation that does not filter them out.
            ->with(['student' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('id')
            ->get();

        $rows = $invoices->map(fn ($invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'student_name' => $invoice->student?->fullName() ?? '—',
            'student_deleted_at' => $invoice->student?->deleted_at?->toDateString(),
            'term' => $invoice->term->name ?? '—',
            'total_cents' => $invoice->total_amount_cents->cents(),
            'paid_cents' => $invoice->paidCents(),
            'payment_count' => $invoice->payments->count(),
            'status' => $invoice->status,
        ]);

        return response()->json([
            'rows' => $rows,
            'count' => $rows->count(),
            'total_billed_cents' => $rows->sum('total_cents'),
            'total_paid_cents' => $rows->sum('paid_cents'),
        ]);
    }

    /**
     * Permanently remove orphaned invoices by id.
     *
     * Only invoices whose student is soft-deleted can be removed here: anything
     * else is silently skipped rather than deleted, so a stale or tampered id
     * list cannot reach a live student's billing.
     */
    public function purgeOrphaned(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $invoices = Invoice::withoutGlobalScope('school')
            ->whereIn('id', $request->input('ids'))
            ->whereHas('student', fn ($q) => $q->onlyTrashed())
            ->with('payments')
            ->get();

        $deleted = 0;
        $paymentsRemoved = 0;

        DB::transaction(function () use ($invoices, &$deleted, &$paymentsRemoved) {
            foreach ($invoices as $invoice) {
                // payments.invoice_id is restrictOnDelete and Payment soft-deletes,
                // so a soft-deleted payment still blocks removing its invoice.
                // invoice_lines cascade and discounts null out at the FK level.
                $payments = $invoice->payments()->withoutGlobalScope('school')->withTrashed()->get();
                $paymentsRemoved += $payments->count();
                $payments->each(fn ($payment) => $payment->forceDelete());

                $invoice->delete();
                $deleted++;
            }
        });

        return response()->json([
            'deleted' => $deleted,
            'payments_removed' => $paymentsRemoved,
            'skipped' => count($request->input('ids')) - $deleted,
        ]);
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
