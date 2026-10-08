<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Pdf\StudentStatementPdf;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every printed document must be written in ONE language — the one the user is
 * working in — not a mix. Receipts used to be Swahili-only, the clearance
 * certificate printed both languages side by side, and the reports were English
 * whatever was chosen.
 *
 * PDFs are compressed binary, so these render each Blade view to HTML and read
 * the text. Fixture data is deliberately language-neutral (names, class, term)
 * so any Swahili/English word found in the output came from the template.
 */
class PrintedDocumentsLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** Words that only a Swahili document would print. */
    private const SWAHILI = [
        'Risiti', 'Asante', 'Ankara', 'Mwanafunzi', 'Salio', 'Muhula', 'Taarifa', 'Malipo', 'Imelipwa',
        'IMELIPWA', 'Jumla', 'Darasa', 'Mzazi', 'Cheti', 'Imetolewa', 'Hakuna', 'Tarehe', 'Kiasi',
        'Mapato', 'Matumizi', 'Madeni', 'Mtaji', 'Mishahara', 'Mhasibu', 'Taslimu', 'Hajalipa', 'Amelipa',
    ];

    /** Words that only an English document would print. */
    private const ENGLISH = [
        'Receipt', 'RECEIPT', 'Thank', 'Invoice', 'INVOICE', 'Student', 'Balance', 'BALANCE', 'Paid', 'PAID',
        'Total', 'TOTAL', 'Class', 'Admission', 'Statement', 'Date', 'DATE', 'Amount', 'Certificate',
        'Signature', 'Payment', 'Parent', 'Guardian', 'Generated', 'Revenue', 'REVENUE', 'Expenses',
        'EXPENSES', 'Assets', 'ASSETS', 'Liabilities', 'Equity', 'Summary', 'Outstanding', 'Issued', 'Cash',
        'Unpaid', 'Partial',
    ];

    private School $school;

    private Student $student;

    private Invoice $invoice;

    private Receipt $receipt;

    private User $accountant;

    private Term $term;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        $this->school = School::create([
            'name' => 'Alpha Academy', 'code' => 'ALP-01', 'slug' => 'alpha-academy',
            'level' => 'primary', 'is_active' => true,
        ]);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Std 1', 'sort_order' => 1]);
        $this->year = AcademicYear::create([
            'school_id' => $this->school->id, 'name' => '2024', 'start_date' => '2024-01-01',
            'end_date' => '2024-12-31', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'academic_year_id' => $this->year->id, 'name' => 'T1', 'number' => 1,
            'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true,
        ]);

        $this->student = Student::create([
            'first_name' => 'Juma', 'last_name' => 'Hassan', 'gender' => 'me', 'status' => 'active',
            'date_of_birth' => '2015-05-04',
        ]);
        Enrollment::create([
            'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'school_class_id' => $class->id, 'academic_year_id' => $this->year->id,
            'admission_number' => 'ALP/0001/2024', 'status' => 'active', 'admitted_at' => '2024-01-10',
        ]);
        $guardian = Guardian::create(['first_name' => 'Neema', 'last_name' => 'Hassan', 'phone' => '0700000000']);
        $guardian->students()->attach($this->student->id, ['relation' => 'mother']);

        $this->accountant = User::factory()->create(['school_id' => $this->school->id, 'name' => 'Zawadi']);
        $this->accountant->assignRole('accountant');

        $this->invoice = Invoice::create([
            'school_id' => $this->school->id, 'student_id' => $this->student->id, 'term_id' => $this->term->id,
            'academic_year_id' => $this->year->id, 'invoice_number' => 'INV-0001', 'total_amount_cents' => 100000,
            'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(),
            'generated_by' => $this->accountant->id,
        ]);
        InvoiceLine::create(['invoice_id' => $this->invoice->id, 'description' => 'Tuition', 'amount_cents' => 100000]);

        $this->receipt = Receipt::create([
            'receipt_number' => 'RCP-0001', 'student_id' => $this->student->id, 'issued_at' => now(),
        ]);
        Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'receipt_id' => $this->receipt->id, 'amount_cents' => 60000, 'method' => 'cash',
            'reference_number' => 'REF123', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);
        $this->invoice->syncStatus();
    }

    /** Render a view as it would print in $locale, reduced to its visible text. */
    private function text(string $view, array $data, string $locale): string
    {
        app()->setLocale($locale);
        $html = view($view, $data)->render();
        app()->setLocale('en');

        $html = preg_replace('#<(style|head|title)\b.*?</\1>#si', ' ', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    }

    private function html(string $view, array $data, string $locale): string
    {
        app()->setLocale($locale);
        $html = view($view, $data)->render();
        app()->setLocale('en');

        return $html;
    }

    private function words(array $list, string $text): array
    {
        return array_values(array_filter(
            $list,
            fn ($w) => preg_match('/(?<![A-Za-z])'.preg_quote($w, '/').'(?![A-Za-z])/u', $text) === 1,
        ));
    }

    /** Assert the text is in $locale only. */
    private function assertOnly(string $locale, string $text, string $what): void
    {
        $foreign = $locale === 'en' ? self::SWAHILI : self::ENGLISH;
        $label = $locale === 'en' ? 'Swahili' : 'English';

        $this->assertSame([], $this->words($foreign, $text), "{$what} printed {$label} wording while the language is '{$locale}'");
    }

    private function receiptData(): array
    {
        $receipt = $this->receipt->fresh();
        $receipt->loadMissing([
            'student.currentEnrollment.schoolClass', 'student.currentEnrollment.school', 'student.guardians',
            'payment.invoice.term', 'payment.invoice.academicYear', 'payment.invoice.lines',
            'payment.invoice.payments', 'payment.recorder',
        ]);
        $lh = SchoolLetterhead::for($this->school);

        return [
            'receipt' => $receipt, 'appName' => $lh['name'], 'appTagline' => $lh['tagline'],
            'logoBase64' => $lh['logo'], 'lh' => $lh,
        ];
    }

    /** A second payment that settles the invoice, so the "fully paid" line prints too. */
    private function settle(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id,
            'amount_cents' => 40000, 'method' => 'mpesa', 'paid_at' => now(), 'recorded_by' => $this->accountant->id,
        ]);
        $this->invoice->syncStatus();
    }

    public function test_receipt_follows_the_language(): void
    {
        $en = $this->text('pdf.receipt', $this->receiptData(), 'en');
        $sw = $this->text('pdf.receipt', $this->receiptData(), 'sw');

        $this->assertStringContainsString('PAYMENT RECEIPT', strtoupper($en));
        $this->assertStringContainsString('Thank you for your payment', $en);
        $this->assertStringContainsString('Cash', $en);
        $this->assertOnly('en', $en, 'English receipt');

        $this->assertStringContainsString('RISITI YA MALIPO', strtoupper($sw));
        $this->assertStringContainsString('Asante kwa malipo yako', $sw);
        $this->assertStringContainsString('Taslimu', $sw);
        $this->assertOnly('sw', $sw, 'Swahili receipt');
    }

    public function test_a_fully_paid_receipt_follows_the_language(): void
    {
        $this->settle();

        $en = $this->text('pdf.receipt', $this->receiptData(), 'en');
        $sw = $this->text('pdf.receipt', $this->receiptData(), 'sw');

        $this->assertStringContainsString('INVOICE FULLY PAID', $en);
        $this->assertStringContainsString('ANKARA IMELIPWA YOTE', $sw);
        $this->assertOnly('en', $en, 'English settled receipt');
        $this->assertOnly('sw', $sw, 'Swahili settled receipt');
    }

    public function test_a_long_receipt_collapses_its_tail_in_the_chosen_language(): void
    {
        foreach (range(1, 9) as $i) {
            InvoiceLine::create(['invoice_id' => $this->invoice->id, 'description' => "Item {$i}", 'amount_cents' => 100]);
        }

        $en = $this->text('pdf.receipt', $this->receiptData(), 'en');
        $sw = $this->text('pdf.receipt', $this->receiptData(), 'sw');

        $this->assertStringContainsString('Other items (2)', $en);
        $this->assertStringContainsString('Vipengele vingine (2)', $sw);
    }

    public function test_the_document_declares_its_language(): void
    {
        $this->assertStringContainsString('<html lang="en">', $this->html('pdf.receipt', $this->receiptData(), 'en'));
        $this->assertStringContainsString('<html lang="sw">', $this->html('pdf.receipt', $this->receiptData(), 'sw'));
    }

    public function test_consolidated_statement_follows_the_language(): void
    {
        $section = app(StudentStatementPdf::class)->buildSection($this->student);
        $data = [
            'student' => $section->student, 'enrollment' => $section->enrollment,
            'statementNumber' => $section->statementNumber, 'invoices' => $section->invoices,
            'totalInvoiced' => $section->totalInvoiced, 'totalPaid' => $section->totalPaid,
            'totalBalance' => $section->totalBalance, 'appName' => $section->appName,
            'appTagline' => $section->appTagline, 'logoBase64' => null, 'lh' => $section->lh,
        ];

        $en = $this->text('pdf.student_statement', $data, 'en');
        $sw = $this->text('pdf.student_statement', $data, 'sw');

        $this->assertStringContainsStringIgnoringCase('Statement of All Payments', $en);
        $this->assertStringContainsString('Partially Paid', $en);
        $this->assertOnly('en', $en, 'English consolidated statement');

        $this->assertStringContainsStringIgnoringCase('Taarifa ya Malipo Yote', $sw);
        $this->assertStringContainsString('Amelipa Kiasi', $sw);
        $this->assertOnly('sw', $sw, 'Swahili consolidated statement');
    }

    public function test_bulk_print_follows_the_language_including_when_nothing_matches(): void
    {
        $section = app(StudentStatementPdf::class)->buildSection($this->student);

        foreach (['en', 'sw'] as $locale) {
            $text = $this->text('pdf.bulk_invoices', ['sections' => collect([$section])], $locale);
            $this->assertOnly($locale, $text, "{$locale} bulk print");

            $empty = $this->text('pdf.bulk_invoices', ['sections' => collect()], $locale);
            $this->assertOnly($locale, $empty, "{$locale} empty bulk print");
            $this->assertStringContainsString(
                $locale === 'en' ? 'No students match this filter.' : 'Hakuna wanafunzi wanaolingana na kigezo hiki.',
                $empty,
            );
        }
    }

    public function test_fee_statement_follows_the_language(): void
    {
        $this->settle();
        $invoices = Invoice::allSchools()->with(['payments', 'term', 'academicYear'])->where('student_id', $this->student->id)->get();
        $lh = SchoolLetterhead::for($this->school);
        $total = $invoices->sum(fn ($i) => $i->total_amount_cents->cents());
        $data = [
            'student' => $this->student, 'enrollment' => $this->student->currentEnrollment, 'school' => $this->school,
            'invoices' => $invoices, 'totalBilled' => $total, 'totalPaid' => $total, 'totalBalance' => 0,
            'appName' => $lh['name'], 'appTagline' => $lh['tagline'], 'logoBase64' => null, 'lh' => $lh,
        ];

        $en = $this->text('pdf.statement', $data, 'en');
        $sw = $this->text('pdf.statement', $data, 'sw');

        $this->assertStringContainsString('ALL FEES PAID', $en);
        $this->assertOnly('en', $en, 'English fee statement');

        $this->assertStringContainsString('ADA YOTE IMELIPWA', $sw);
        $this->assertOnly('sw', $sw, 'Swahili fee statement');
    }

    public function test_clearance_certificate_is_one_language_not_both(): void
    {
        $data = [
            'student' => $this->student, 'enrollment' => $this->student->currentEnrollment, 'school' => $this->school,
            'academicYear' => $this->year, 'issuedAt' => now()->setDate(2026, 10, 7), 'issuedBy' => $this->accountant,
            'logoBase64' => null,
        ];

        $en = $this->text('pdf.clearance', $data, 'en');
        $sw = $this->text('pdf.clearance', $data, 'sw');

        $this->assertStringContainsString('Clearance Certificate', $en);
        $this->assertStringContainsString('settled all school fees in full', $en);
        $this->assertStringContainsString('7 October 2026', $en);
        $this->assertStringContainsString('Male', $en);
        $this->assertOnly('en', $en, 'English clearance certificate');

        $this->assertStringContainsString('Cheti cha Usafi wa Madeni', $sw);
        $this->assertStringContainsString('Oktoba', $sw, 'the date must use Swahili month names');
        $this->assertStringContainsString('Kiume', $sw);
        $this->assertOnly('sw', $sw, 'Swahili clearance certificate');

        // The "&mdash;" placeholders used to print literally.
        $this->assertStringNotContainsString('&mdash;', $this->html('pdf.clearance', $data, 'en'));
    }

    public function test_reports_follow_the_language(): void
    {
        $school = $this->school;
        $buckets = [];
        foreach (['current', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90'] as $key) {
            $buckets[$key] = ['count' => 0, 'amount_cents' => 0, 'students' => []];
        }
        $buckets['days_1_30'] = ['count' => 1, 'amount_cents' => 40000, 'students' => [[
            'full_name' => 'Juma Hassan', 'admission_number' => 'ALP/0001/2024', 'school_class' => 'Std 1',
            'oldest_invoice_date' => '2024-02-01', 'outstanding_cents' => 40000,
        ]]];

        $reports = [
            'pdf.reports.collections' => ['report' => [
                'period' => ['from' => '2024-01-01', 'to' => '2024-03-31'],
                'summary' => ['total_payments' => 2, 'total_amount_cents' => 100000, 'invoice_count' => 1,
                    'paid_count' => 1, 'partial_count' => 0, 'unpaid_count' => 0],
                'rows' => [['period' => '2024-01', 'payment_count' => 2, 'amount_cents' => 100000]],
                'by_method' => [['method' => 'cash', 'count' => 2, 'amount_cents' => 100000]],
                'by_class' => [['class' => 'Std 1', 'amount_cents' => 100000]],
            ], 'school' => $school],
            'pdf.reports.debtor_aging' => ['report' => [
                'summary' => ['as_of' => '2024-03-31', 'total_debtors' => 1, 'total_outstanding_cents' => 40000],
                'buckets' => $buckets,
            ], 'school' => $school],
            'pdf.reports.income_statement' => ['report' => [
                'period' => ['from' => '2024-01-01', 'to' => '2024-03-31'],
                'revenue' => ['fee_collections' => 100000, 'total' => 100000],
                'expenses' => ['by_category' => [['category' => 'Fuel', 'amount_cents' => 5000]], 'payroll' => 20000, 'total' => 25000],
                'net_income_cents' => 75000,
            ], 'school' => $school],
            'pdf.reports.balance_sheet' => ['report' => [
                'as_of' => '2024-03-31',
                'assets' => [
                    'cash_and_bank' => ['description' => 'x', 'amount_cents' => 1],
                    'receivables' => ['description' => 'x', 'amount_cents' => 1],
                    'fixed_assets' => ['description' => 'x', 'amount_cents' => 1],
                    'total' => 3,
                ],
                'liabilities' => ['payables' => ['description' => 'x', 'amount_cents' => 1], 'total' => 1],
                'equity' => ['retained' => 2, 'total' => 2],
            ], 'school' => $school],
            'pdf.reports.student_statement' => ['report' => [
                'student' => ['full_name' => 'Juma Hassan', 'admission_number' => 'ALP/0001/2024', 'school_class' => 'Std 1',
                    'academic_year' => '2024', 'school' => 'Alpha Academy'],
                'invoices' => [[
                    'invoice_number' => 'INV-0001', 'due_date' => '2024-02-01', 'term' => 'T1', 'gross_cents' => 100000,
                    'paid_cents' => 60000, 'balance_cents' => 40000, 'status' => 'partial',
                    'payments' => [['paid_at' => '2024-01-20', 'method' => 'cash', 'amount_cents' => 60000, 'reference_number' => 'REF123']],
                ]],
                'total_invoiced_cents' => 100000, 'total_paid_cents' => 60000, 'balance_cents' => 40000,
            ]],
        ];

        foreach ($reports as $view => $data) {
            foreach (['en', 'sw'] as $locale) {
                $text = $this->text($view, $data, $locale);
                $this->assertOnly($locale, $text, "{$view} ({$locale})");
            }
        }
    }

    public function test_the_receipt_endpoint_renders_in_both_languages(): void
    {
        $this->app['auth']->forgetGuards();
        $token = $this->accountant->createToken('t')->plainTextToken;

        foreach (['en', 'sw'] as $locale) {
            $response = $this->withToken($token)->withHeader('X-Lang', $locale)
                ->get("/api/receipts/{$this->receipt->id}/download");

            $response->assertOk();
            $this->assertStringContainsString('%PDF', substr($response->getContent(), 0, 10));
        }
    }

    public function test_enum_labels_and_api_labels_follow_the_language(): void
    {
        $this->app['auth']->forgetGuards();
        $token = $this->accountant->createToken('t')->plainTextToken;

        $sw = $this->withToken($token)->withHeader('X-Lang', 'sw')->getJson('/api/payments?per_page=5');
        $en = $this->withToken($token)->withHeader('X-Lang', 'en')->getJson('/api/payments?per_page=5');

        $this->assertSame('Taslimu', $sw->json('data.0.method_label'));
        $this->assertSame('Cash', $en->json('data.0.method_label'));
    }
}
