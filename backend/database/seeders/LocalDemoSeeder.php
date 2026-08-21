<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payroll;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LocalDemoSeeder extends Seeder
{
    private School $msingi;
    private School $sekondari;
    private AcademicYear $year;
    private Term $term1;
    private User $accountant;

    private array $male   = ['John','James','Peter','Michael','David','Samuel','Daniel','Joseph','Emmanuel','George','Robert','Charles','Patrick','Francis','Thomas'];
    private array $female = ['Mary','Anna','Grace','Faith','Joyce','Beatrice','Agnes','Esther','Sarah','Amina','Rehema','Fatuma','Zuhura','Hadija','Salma'];
    private array $lastN  = ['Mwangi','Ochieng','Kamau','Otieno','Wanjiru','Mugo','Njoroge','Kimani','Maina','Kariuki','Magaiwa','Massawe','Swai','Lema','Mushi','Nkya','Mbise','Urassa','Mrema','Kimaro'];

    public function run(): void
    {
        $schools = School::orderBy('id')->get();
        if ($schools->isEmpty()) {
            $this->command->warn('No schools found — skipping LocalDemoSeeder.');
            return;
        }
        $this->msingi    = $schools->first();
        $this->sekondari = $schools->count() > 1 ? $schools->get(1) : $this->msingi;

        $this->year = AcademicYear::where('school_id', $this->msingi->id)->where('is_current', true)->first()
                   ?? AcademicYear::where('school_id', $this->msingi->id)->latest()->first();

        if (! $this->year) {
            $this->command->warn('No academic year found — skipping LocalDemoSeeder.');
            return;
        }

        $this->term1 = Term::where('academic_year_id', $this->year->id)->orderBy('number')->first();

        $this->accountant = User::where('email', 'accountant@gmail.com')->first()
                         ?? User::where('school_id', $this->msingi->id)->first();

        $this->seedEmployees();
        $this->seedStudentsAndGuardians();
        $this->seedExpenses();
        $this->seedPayroll();
        $this->seedInventoryTransactions();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function firstName(string $gender, int $n): string
    {
        return $gender === 'male'
            ? $this->male[$n % count($this->male)]
            : $this->female[$n % count($this->female)];
    }

    private function lastName(int $n): string
    {
        return $this->lastN[$n % count($this->lastN)];
    }

    private function phone(int $seed): string
    {
        return '07'.str_pad((string) (($seed * 97 + 123456) % 100000000), 8, '0', STR_PAD_LEFT);
    }

    private function salaryForRole(string $role): int
    {
        return match ($role) {
            'headmaster', 'head_teacher' => 1_400_000_00,
            'academic_pri', 'academic_sec' => 1_100_000_00,
            'accountant'                  => 1_000_000_00,
            default                       => 800_000_00,
        };
    }

    // ── Employees ──────────────────────────────────────────────────────────────
    private function seedEmployees(): void
    {
        $groups = [
            ['school' => $this->msingi,    'prefix' => 'MPS', 'start' => 1,  'roles' => [
                ['role' => 'teacher_pri',  'dept' => 'Elimu',   'count' => 8],
                ['role' => 'head_teacher', 'dept' => 'Uongozi', 'count' => 1],
                ['role' => 'academic_pri', 'dept' => 'Elimu',   'count' => 2],
                ['role' => 'accountant',   'dept' => 'Fedha',   'count' => 1],
            ]],
            ['school' => $this->sekondari, 'prefix' => 'MMS', 'start' => 20, 'roles' => [
                ['role' => 'teacher_sec',  'dept' => 'Elimu',   'count' => 6],
                ['role' => 'headmaster',   'dept' => 'Uongozi', 'count' => 1],
                ['role' => 'academic_sec', 'dept' => 'Elimu',   'count' => 2],
            ]],
        ];

        foreach ($groups as $group) {
            $n = $group['start'];
            foreach ($group['roles'] as $r) {
                for ($i = 0; $i < $r['count']; $i++, $n++) {
                    $staffNum = $group['prefix'].'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                    Employee::firstOrCreate(
                        ['staff_number' => $staffNum],
                        [
                            'school_id'          => $group['school']->id,
                            'full_name'          => $this->firstName($n % 2 === 0 ? 'male' : 'female', $n).' '.$this->lastName($n),
                            'role'               => $r['role'],
                            'department'         => $r['dept'],
                            'hire_date'          => Carbon::now()->subYears(rand(1, 8))->subMonths(rand(0, 11))->toDateString(),
                            'basic_salary_cents' => $this->salaryForRole($r['role']),
                            'status'             => 'active',
                        ]
                    );
                }
            }
        }
    }

    // ── Students & Guardians ───────────────────────────────────────────────────
    private function seedStudentsAndGuardians(): void
    {
        $classes = SchoolClass::where('school_id', $this->msingi->id)->get();
        if ($classes->isEmpty()) return;

        $adm = 1000;
        $n   = 1;

        foreach ($classes as $class) {
            for ($i = 0; $i < 12; $i++, $n++) {
                $gender = $n % 2 === 0 ? 'male' : 'female';
                $admNo  = 'MPS/'.$adm++;

                // Guard: skip if already enrolled in this class+year
                $existing = Enrollment::where('admission_number', $admNo)
                    ->where('academic_year_id', $this->year->id)
                    ->first();
                if ($existing) continue;

                $student = Student::create([
                    'first_name'    => $this->firstName($gender, $n),
                    'last_name'     => $this->lastName($n),
                    'gender'        => $gender,
                    'date_of_birth' => Carbon::now()->subYears(rand(6, 15))->toDateString(),
                    'status'        => 'active',
                ]);

                Enrollment::create([
                    'student_id'       => $student->id,
                    'school_id'        => $this->msingi->id,
                    'school_class_id'  => $class->id,
                    'academic_year_id' => $this->year->id,
                    'term_id'          => $this->term1?->id,
                    'admission_number' => $admNo,
                    'status'           => 'active',
                    'admitted_at'      => $this->year->start_date ?? Carbon::now()->startOfYear()->toDateString(),
                ]);

                // One guardian per 3 students (shared family)
                if ($n % 3 === 1) {
                    $guardian = Guardian::create([
                        'first_name' => $this->firstName('male', $n + 50),
                        'last_name'  => $this->lastName($n),
                        'phone'      => $this->phone($n + 500),
                    ]);
                    $student->guardians()->attach($guardian->id, [
                        'relation'     => 'baba',
                        'is_primary'   => true,
                        'receives_sms' => true,
                    ]);
                }

                // Create invoice for this student if fee structure exists
                $this->createInvoice($student, $class);
            }
        }
    }

    private function createInvoice(Student $student, SchoolClass $class): void
    {
        if (! $this->term1) return;

        $feeStructure = FeeStructure::where('school_id', $this->msingi->id)
            ->where('academic_year_id', $this->year->id)
            ->where('term_id', $this->term1->id)
            ->with('feeItems')
            ->first();

        if (! $feeStructure || $feeStructure->feeItems->isEmpty()) return;

        $total = (int) $feeStructure->feeItems->sum(fn ($i) => $i->getRawOriginal('amount_cents'));
        $invNo = 'INV-'.strtoupper(substr(uniqid(), -6));

        $invoice = Invoice::create([
            'student_id'        => $student->id,
            'school_id'         => $this->msingi->id,
            'term_id'           => $this->term1->id,
            'academic_year_id'  => $this->year->id,
            'invoice_number'    => $invNo,
            'total_amount_cents'=> $total,
            'discount_cents'    => 0,
            'arrears_cents'     => 0,
            'status'            => 'unpaid',
            'due_date'          => Carbon::parse($this->term1->start_date ?? now())->addDays(30)->toDateString(),
            'generated_at'      => now(),
            'generated_by'      => $this->accountant->id,
        ]);

        foreach ($feeStructure->feeItems as $item) {
            InvoiceLine::create([
                'invoice_id'   => $invoice->id,
                'fee_item_id'  => $item->id,
                'description'  => $item->name,
                'amount_cents' => $item->getRawOriginal('amount_cents'),
            ]);
        }

        // 70% chance of full or partial payment
        $rand = rand(1, 100);
        if ($rand <= 40) {
            // Full
            $invoice->update(['status' => 'paid']);
            Payment::create([
                'invoice_id'       => $invoice->id,
                'student_id'       => $student->id,
                'school_id'        => $this->msingi->id,
                'amount_cents'     => $total,
                'method'           => ['cash','mpesa','bank'][rand(0,2)],
                'reference_number' => 'REF'.rand(100000, 999999),
                'paid_at'          => Carbon::now()->subDays(rand(1, 30)),
                'recorded_by'      => $this->accountant->id,
            ]);
        } elseif ($rand <= 70) {
            // Partial
            $paid = (int) ($total * (rand(30, 80) / 100));
            $invoice->update(['status' => 'partial']);
            Payment::create([
                'invoice_id'       => $invoice->id,
                'student_id'       => $student->id,
                'school_id'        => $this->msingi->id,
                'amount_cents'     => $paid,
                'method'           => ['cash','mpesa'][rand(0,1)],
                'reference_number' => 'REF'.rand(100000, 999999),
                'paid_at'          => Carbon::now()->subDays(rand(1, 20)),
                'recorded_by'      => $this->accountant->id,
            ]);
        }
    }

    // ── Expenses ───────────────────────────────────────────────────────────────
    private function seedExpenses(): void
    {
        $catNames = ['Umeme', 'Maji', 'Chakula', 'Matengenezo', 'Usafiri', 'Ofisi'];

        $catIds = [];
        foreach ($catNames as $name) {
            $c = ExpenseCategory::firstOrCreate(
                ['name' => $name, 'school_id' => $this->msingi->id]
            );
            $catIds[] = $c->id;
        }

        $descriptions = [
            'Bili ya umeme', 'Vifaa vya ofisi', 'Matengenezo ya darasa',
            'Mafuta ya basi', 'Chakula cha walimu', 'Bili ya maji',
            'Uchapishaji', 'Vifaa vya usafi', 'Matengenezo ya kompyuta', 'Dawa',
        ];

        for ($i = 0; $i < 30; $i++) {
            Expense::create([
                'school_id'    => $this->msingi->id,
                'category_id'  => $catIds[$i % count($catIds)],
                'description'  => $descriptions[$i % count($descriptions)],
                'amount_cents' => rand(5, 500) * 100_00,
                'expense_date' => Carbon::now()->subDays(rand(1, 180))->toDateString(),
                'status'       => ['approved','pending','approved','approved'][$i % 4],
                'recorded_by'  => $this->accountant->id,
            ]);
        }
    }

    // ── Payroll ────────────────────────────────────────────────────────────────
    private function seedPayroll(): void
    {
        $employees = Employee::where('school_id', $this->msingi->id)->where('status', 'active')->get();

        $periods = [
            ['year' => (int) Carbon::now()->subMonths(2)->format('Y'), 'month' => (int) Carbon::now()->subMonths(2)->format('n'), 'paid' => true],
            ['year' => (int) Carbon::now()->subMonth()->format('Y'),   'month' => (int) Carbon::now()->subMonth()->format('n'),   'paid' => false],
        ];

        foreach ($periods as $p) {
            foreach ($employees as $emp) {
                Payroll::firstOrCreate(
                    ['employee_id' => $emp->id, 'month' => $p['month'], 'year' => $p['year']],
                    [
                        'school_id'          => $this->msingi->id,
                        'basic_salary_cents' => $emp->getRawOriginal('basic_salary_cents'),
                        'allowances_cents'   => 0,
                        'deductions_cents'   => (int) ($emp->getRawOriginal('basic_salary_cents') * 0.05),
                        'net_salary_cents'   => (int) ($emp->getRawOriginal('basic_salary_cents') * 0.95),
                        'payment_method'     => 'bank',
                        'status'             => $p['paid'] ? 'paid' : 'pending',
                        'payment_date'       => $p['paid']
                            ? Carbon::create($p['year'], $p['month'], 28)->toDateString()
                            : null,
                        'recorded_by'        => $this->accountant->id,
                    ]
                );
            }
        }
    }

    // ── Inventory Transactions ─────────────────────────────────────────────────
    private function seedInventoryTransactions(): void
    {
        $items    = InventoryItem::where('school_id', $this->msingi->id)
            ->where('type', 'consumable')
            ->get();
        $employee = Employee::where('school_id', $this->msingi->id)->first();

        foreach ($items as $item) {
            InventoryTransaction::firstOrCreate(
                ['item_id' => $item->id, 'type' => 'in', 'reference' => 'SEED-IN-'.$item->id],
                [
                    'school_id'       => $this->msingi->id,
                    'type'            => 'in',
                    'quantity'        => 20,
                    'notes'           => 'Ununuzi wa awali',
                    'transaction_date'=> Carbon::now()->subDays(60)->toDateString(),
                    'recorded_by'     => $this->accountant->id,
                ]
            );

            if ($employee) {
                InventoryTransaction::firstOrCreate(
                    ['item_id' => $item->id, 'type' => 'out', 'issued_to_employee_id' => $employee->id],
                    [
                        'school_id'             => $this->msingi->id,
                        'type'                  => 'out',
                        'quantity'              => rand(2, 8),
                        'issued_to'             => $employee->full_name,
                        'issued_to_employee_id' => $employee->id,
                        'notes'                 => 'Mgawanyo wa vifaa',
                        'transaction_date'      => Carbon::now()->subDays(30)->toDateString(),
                        'recorded_by'           => $this->accountant->id,
                    ]
                );
            }
        }
    }
}
