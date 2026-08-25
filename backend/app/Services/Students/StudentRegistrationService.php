<?php

namespace App\Services\Students;

use App\Models\Discount;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Payments\ReceiptService;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentRegistrationService
{
    public function register(array $data, ?UploadedFile $photo = null): Student
    {
        return DB::transaction(function () use ($data, $photo) {

            // 0. Idempotency guard: if an identical student (same name + DOB, same school)
            // was created moments ago, this is almost certainly a duplicate submission
            // (double-click, slow-network retry) — return the existing record instead of
            // creating a second one.
            $duplicate = Student::where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->where('date_of_birth', $data['date_of_birth'])
                ->whereHas('enrollments', fn ($q) => $q->where('school_id', $data['school_id']))
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();

            if ($duplicate) {
                return $duplicate->load(['enrollments', 'guardians.user', 'currentEnrollment.schoolClass']);
            }

            // 1. Handle photo upload
            $photoPath = null;
            if ($photo) {
                $photoPath = $photo->store('student-photos', 'public');
            }

            // 2. Resolve admission number (use provided or auto-generate)
            $admissionNo = $data['admission_no'] ?? null;
            if (empty($admissionNo)) {
                $school = School::findOrFail($data['school_id']);
                $admissionNo = $school->nextAdmissionNumber();
            }

            // 3. Create student
            $student = Student::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'birth_certificate_no' => $data['birth_certificate_no'] ?? null,
                'nationality' => $data['nationality'] ?? 'Tanzanian',
                'religion' => $data['religion'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'allergies' => $data['allergies'] ?? null,
                'medical_conditions' => $data['medical_conditions'] ?? null,
                'address' => $data['address'] ?? null,
                'region' => $data['region'] ?? null,
                'district' => $data['district'] ?? null,
                'ward' => $data['ward'] ?? null,
                'street' => $data['street'] ?? null,
                'photo' => $photoPath,
                'status' => $data['status'] ?? 'active',
                'sponsorship_type' => $data['sponsorship_type'] ?? 'none',
                'notes' => $data['notes'] ?? null,
            ]);

            // 4. Create enrollment
            $student->enrollments()->create([
                'school_id' => $data['school_id'],
                'school_class_id' => $data['school_class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'term_id' => $data['term_id'],
                'admission_number' => $admissionNo,
                'admitted_at' => $data['enrollment_date'] ?? now()->toDateString(),
                'previous_school' => $data['previous_school'] ?? null,
                'status' => 'active',
            ]);

            // 5. Save identifications
            if (! empty($data['identifications'])) {
                $hasPrimary = false;
                foreach ($data['identifications'] as $idx => $idData) {
                    $isPrimary = ! $hasPrimary && (! isset($idData['is_primary']) || $idData['is_primary']);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                    $student->identifications()->create([
                        'type' => $idData['type'],
                        'number' => $idData['number'],
                        'expires_at' => $idData['expires_at'] ?? null,
                        'is_primary' => $isPrimary,
                    ]);
                }
            }

            // 6. Link/create guardians
            foreach ($data['guardians'] as $gData) {
                $guardian = $this->resolveGuardian($gData);

                $student->guardians()->syncWithoutDetaching([
                    $guardian->id => [
                        'relation' => $gData['relationship'],
                        'is_primary' => (bool) ($gData['is_primary_contact'] ?? false),
                        'receives_sms' => true,
                    ],
                ]);
            }

            // A fully-sponsored student has no billing at all — enforced here, not just
            // hidden in the UI, so a stale/tampered request can't sneak invoices in for a
            // student who is supposed to owe nothing.
            // Only 'full' means no billing whatsoever. 'full_paid' is fully sponsored
            // but payments are still recorded against a fee, so it bills like any
            // other student — hence the exact match rather than a str_starts_with.
            $isFullySponsored = ($data['sponsorship_type'] ?? 'none') === 'full';

            // 6. Migration: create backdated invoices + payments for existing students
            $isExisting = ! empty($data['is_existing_student']);
            $migrationMode = $data['migration_mode'] ?? 'detailed';

            if ($isExisting && ! $isFullySponsored) {
                if ($migrationMode === 'lumpsum') {
                    // Annual summary mode: create single consolidated invoice
                    $this->importLumpsumPaymentHistory($student, $data);
                } elseif (! empty($data['payment_history'])) {
                    // Detailed mode: invoice per term
                    $this->importPaymentHistory($student, $data['school_id'], $data['payment_history']);
                }
            }

            // 7. Generate first invoice if requested (skip for existing — history covers it)
            $generateInvoice = ! $isExisting && ! $isFullySponsored && (isset($data['generate_first_invoice'])
                ? (bool) $data['generate_first_invoice']
                : true);

            if ($generateInvoice) {
                $this->generateInvoice($student, $data);
            }

            // 7. Audit log
            AuditLogger::log('student.registered', $student, [
                'admission_number' => $admissionNo,
                'school_id' => $data['school_id'],
                'guardians_count' => count($data['guardians']),
            ]);

            $student->load(['enrollments', 'guardians.user', 'currentEnrollment.schoolClass']);

            // 8. Welcome SMS
            try {
                $school = School::findOrFail($data['school_id']);
                $schoolClass = $student->currentEnrollment?->schoolClass;
                $className = $schoolClass?->name ?? '';
                $message = SmsTemplates::studentWelcome($student, $school->name, $className);
                app(SmsService::class)->notifyGuardians($student, $message);
            } catch (\Throwable $e) {
                Log::warning('[StudentRegistrationService] Welcome SMS failed: '.$e->getMessage());
            }

            return $student;
        });
    }

    private function resolveGuardian(array $gData): Guardian
    {
        $gData['phone'] = self::normalizePhone($gData['phone']);
        $gData['alt_phone'] = isset($gData['alt_phone']) && $gData['alt_phone']
            ? self::normalizePhone($gData['alt_phone']) : null;

        // Find existing user by phone — reuse guardian if found
        $existingUser = User::where('phone', $gData['phone'])->first();

        if ($existingUser) {
            // Reuse existing guardian, or create one for the existing user
            if ($existingUser->guardian) {
                return $existingUser->guardian;
            }

            return Guardian::create([
                'user_id' => $existingUser->id,
                'first_name' => $this->firstName($gData['full_name']),
                'last_name' => $this->lastName($gData['full_name']),
                'phone' => $gData['phone'],
                'email' => $existingUser->email,
                'national_id' => $gData['national_id'] ?? null,
                'address' => $gData['address'] ?? null,
            ]);
        }

        // Create a new user account for the guardian
        $email = ! empty($gData['email']) ? $gData['email'] : null;

        // If email already taken, null it out and rely on phone only
        if ($email && User::where('email', $email)->exists()) {
            $email = null;
        }

        $user = User::create([
            'name' => $gData['full_name'],
            'email' => $email ?? $gData['phone'].'@guardian.local',
            'phone' => $gData['phone'],
            'password' => bcrypt(Str::random(16)),
            'role' => 'parent',
        ]);

        $user->assignRole('parent');

        return Guardian::create([
            'user_id' => $user->id,
            'first_name' => $this->firstName($gData['full_name']),
            'last_name' => $this->lastName($gData['full_name']),
            'phone' => $gData['phone'],
            'email' => $email,
            'national_id' => $gData['national_id'] ?? null,
            'address' => $gData['address'] ?? null,
        ]);
    }

    /**
     * Normalize phone to E.164 Tanzania format (255XXXXXXXXX, 12 digits).
     * Accepts: 07XXXXXXXX, +255XXXXXXXXX, 255XXXXXXXXX, 7XXXXXXXX, 6XXXXXXXX.
     */
    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }
        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }
        if ((str_starts_with($digits, '7') || str_starts_with($digits, '6')) && strlen($digits) === 9) {
            return '255'.$digits;
        }

        return $digits; // return as-is if unrecognized
    }

    private function firstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return $parts[0] ?? $fullName;
    }

    private function lastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));

        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $fullName;
    }

    private function importPaymentHistory(Student $student, int $schoolId, array $history): void
    {
        foreach ($history as $entry) {
            $termId = $entry['term_id'] ?? null;
            $yearId = $entry['academic_year_id'] ?? null;
            $feeCents = (int) ($entry['fee_amount_cents'] ?? 0);
            $payments = $entry['payments'] ?? [];

            // Skip incomplete entries
            if (! $termId || ! $yearId || $feeCents <= 0) {
                continue;
            }

            // Skip if invoice already exists for this student+term (idempotent)
            if (Invoice::withoutGlobalScope('school')
                ->where('student_id', $student->id)
                ->where('school_id', $schoolId)
                ->where('term_id', $termId)
                ->exists()) {
                continue;
            }

            $invoice = Invoice::withoutGlobalScope('school')->create([
                'student_id' => $student->id,
                'school_id' => $schoolId,
                'term_id' => $termId,
                'academic_year_id' => $yearId,
                'invoice_number' => $this->nextMigrationNumber($schoolId),
                'total_amount_cents' => $feeCents,
                'arrears_cents' => 0,
                'discount_cents' => 0,
                'status' => 'unpaid',
                'due_date' => null,
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]);

            $invoice->lines()->create([
                'fee_item_id' => null,
                'description' => 'Ada ya muhula',
                'amount_cents' => $feeCents,
            ]);

            foreach ($payments as $p) {
                $amountCents = (int) ($p['amount_cents'] ?? 0);
                if ($amountCents <= 0 || empty($p['paid_at'])) {
                    continue; // skip malformed payment rows
                }

                // Guard: don't allow overpayment beyond invoice total
                $alreadyPaid = $invoice->paidCents();
                $cap = $feeCents - $alreadyPaid;
                if ($cap <= 0) {
                    break;
                }
                $amountCents = min($amountCents, $cap);

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    // Migrated payments get a receipt too, otherwise the receipt button
                    // is permanently unavailable for every student brought over from books.
                    'receipt_id' => app(ReceiptService::class)->issue($student->id)->id,
                    'amount_cents' => $amountCents,
                    'method' => $p['method'] ?? 'cash',
                    'reference_number' => null,
                    'paid_at' => $p['paid_at'],
                    'recorded_by' => auth()->id(),
                    'notes' => trim($p['notes'] ?? '') ?: 'Imehamishwa kutoka vitabuni',
                ]);
            }

            $invoice->syncStatus();
        }
    }

    /** Generate the next migration invoice number for this school.
     *  Format: {code}-{year}-{000001} — the school code stands in for "MIG"
     *  entirely (e.g. MGRTHMR-2026-000001 for secondary, MGRTH-2026-000001 for
     *  primary), keeping the original dash-separated, 6-digit sequence style.
     */
    private function nextMigrationNumber(int $schoolId): string
    {
        $year = date('Y');
        $school = School::find($schoolId);
        $code = strtoupper($school->code ?? 'SCH');

        // Scoped to school_id, not the code string, for the same reason admission
        // numbers are — renaming a school's code later must not reset the sequence.
        $seq = Invoice::allSchools()
            ->where('school_id', $schoolId)
            ->where('invoice_number', 'like', "{$code}-{$year}-%")
            ->get(['invoice_number'])
            ->map(fn ($inv) => preg_match('#^'.$code.'-'.$year.'-(\d+)$#', $inv->invoice_number, $m) ? (int) $m[1] : 0)
            ->max();

        return sprintf('%s-%s-%06d', $code, $year, ((int) $seq) + 1);
    }

    private function generateInvoice(Student $student, array $data): void
    {
        $openingBalance = (int) ($data['opening_balance_cents'] ?? 0);
        $discountCents = (int) ($data['discount_amount_cents'] ?? 0);
        $manualTuitionCents = (int) ($data['total_tuition_fee_cents'] ?? 0);

        // Try to load fee structure from class/term
        $feeStructure = FeeStructure::where('school_id', $data['school_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('term_id', $data['term_id'])
            ->where('is_active', true)
            ->with('feeItems')
            ->first();

        // Determine total amount: use fee structure if available, otherwise use manual input
        $totalCents = $feeStructure
            ? $feeStructure->totalCents()
            : ($manualTuitionCents > 0 ? $manualTuitionCents : null);

        // Skip if no fee structure AND no manual tuition fee provided
        if ($totalCents === null) {
            return;
        }

        // Create invoice
        $invoice = Invoice::create([
            'student_id' => $student->id,
            'school_id' => $data['school_id'],
            'term_id' => $data['term_id'],
            'academic_year_id' => $data['academic_year_id'],
            'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
            'total_amount_cents' => $totalCents,
            'arrears_cents' => $openingBalance,
            'discount_cents' => 0,
            'status' => 'unpaid',
            'due_date' => now()->addDays(30)->toDateString(),
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ]);

        // Create invoice lines from fee structure items (if exists)
        if ($feeStructure) {
            foreach ($feeStructure->feeItems as $item) {
                $invoice->lines()->create([
                    'fee_item_id' => $item->id,
                    'description' => $item->name,
                    'amount_cents' => $item->amount_cents->cents(),
                ]);
            }
        } else {
            // Fallback: create a single generic line item when using manual tuition fee
            $invoice->lines()->create([
                'fee_item_id' => null,
                'description' => 'Manual Tuition Fee',
                'amount_cents' => $totalCents,
            ]);
        }

        // Apply discount if provided
        if (! empty($data['discount_type']) && $discountCents > 0) {
            Discount::create([
                'student_id' => $student->id,
                'invoice_id' => $invoice->id,
                'type' => $data['discount_type'],
                'amount_cents' => $discountCents,
                'is_percentage' => false,
                'reason' => $data['discount_type'],
                'applied_by' => auth()->id(),
            ]);

            $invoice->discount_cents = $discountCents;
            $invoice->save();
        }

        // 'full_paid' sponsorship: the sponsor covers a fixed amount of the fee,
        // separate from the fee itself. Record it as a payment against the
        // invoice — same as any other payment — so the balance due is exactly
        // the fee minus what the sponsor already covered.
        if (($data['sponsorship_type'] ?? 'none') === 'full_paid') {
            $sponsoredCents = (int) ($data['sponsored_amount_cents'] ?? 0);
            if ($sponsoredCents > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'student_id' => $student->id,
                    'school_id' => $data['school_id'],
                    'receipt_id' => app(ReceiptService::class)->issue($student->id)->id,
                    'amount_cents' => min($sponsoredCents, $totalCents),
                    'method' => 'sponsor',
                    'reference_number' => null,
                    'paid_at' => now()->toDateString(),
                    'recorded_by' => auth()->id(),
                    'notes' => 'Sponsor payment recorded at registration',
                ]);
            }
        }

        $invoice->syncStatus();
    }

    /**
     * Powerful lump-sum migration: Create single consolidated invoice for annual summary
     * Handles payments made all at once or in chunks, without needing term-by-term breakdown
     */
    private function importLumpsumPaymentHistory(Student $student, array $data): void
    {
        $schoolId = $data['school_id'];
        $totalChargedCents = (int) ($data['lumpsum_total_charged_cents'] ?? 0);
        $totalPaidCents = (int) ($data['lumpsum_total_paid_cents'] ?? 0);

        // Validation: must have charged amount
        if ($totalChargedCents <= 0) {
            return;
        }

        // Create master invoice covering all historical years.
        // term_id/academic_year_id are NOT NULL foreign keys on the invoices table, so we
        // anchor the migrated lumpsum invoice to the student's current enrollment term/year
        // (collected in Step 3) rather than null — it represents when the migration record
        // was entered, since the underlying history predates term-by-term tracking.
        $invoice = Invoice::withoutGlobalScope('school')->create([
            'student_id' => $student->id,
            'school_id' => $schoolId,
            'term_id' => $data['term_id'],
            'academic_year_id' => $data['academic_year_id'],
            'invoice_number' => $this->nextMigrationNumber($schoolId),
            'total_amount_cents' => $totalChargedCents,
            'arrears_cents' => 0,
            'discount_cents' => 0,
            'status' => 'unpaid',
            'due_date' => null,
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ]);

        // Create single invoice line for complete history
        $invoice->lines()->create([
            'fee_item_id' => null,
            'description' => 'Jumla ya ada kutoka vitabuni (Ada iliyohamishwa)',
            'amount_cents' => $totalChargedCents,
        ]);

        // Record payment if amount was paid
        if ($totalPaidCents > 0) {
            // Cap payment to invoice total (no overpayment)
            $paymentAmount = min($totalPaidCents, $totalChargedCents);

            // Use the historical payment date supplied by the user — defaulting to
            // "now" here would misdate every migrated lumpsum payment as made today,
            // permanently inflating the dashboard's "Today's Collections" figure for
            // money that was actually collected on a past date.
            $paidAt = $data['lumpsum_payment_date'] ?? now()->toDateString();

            Payment::create([
                'invoice_id' => $invoice->id,
                'student_id' => $student->id,
                'school_id' => $schoolId,
                'receipt_id' => app(ReceiptService::class)->issue($student->id)->id,
                'amount_cents' => $paymentAmount,
                'method' => 'cash',
                'reference_number' => null,
                'paid_at' => $paidAt,
                'recorded_by' => auth()->id(),
                'notes' => 'Imehamishwa kutoka vitabuni - Jumla ya malipo ya juu',
            ]);
        }

        // Sync invoice status based on payment
        $invoice->syncStatus();
    }
}
