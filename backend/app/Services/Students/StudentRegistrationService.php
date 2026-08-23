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

            // 6. Migration: create backdated invoices + payments for existing students
            $isExisting = ! empty($data['is_existing_student']);
            if ($isExisting && ! empty($data['payment_history'])) {
                $this->importPaymentHistory($student, $data['school_id'], $data['payment_history']);
            }

            // 7. Generate first invoice if requested (skip for existing — history covers it)
            $generateInvoice = ! $isExisting && (isset($data['generate_first_invoice'])
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
            $termId   = $entry['term_id']          ?? null;
            $yearId   = $entry['academic_year_id'] ?? null;
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
                'student_id'         => $student->id,
                'school_id'          => $schoolId,
                'term_id'            => $termId,
                'academic_year_id'   => $yearId,
                'invoice_number'     => $this->nextMigrationNumber(),
                'total_amount_cents' => $feeCents,
                'arrears_cents'      => 0,
                'discount_cents'     => 0,
                'status'             => 'unpaid',
                'due_date'           => null,
                'generated_at'       => now(),
                'generated_by'       => auth()->id(),
            ]);

            $invoice->lines()->create([
                'fee_item_id'  => null,
                'description'  => 'Ada iliyohamishwa (kutoka vitabuni)',
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
                if ($cap <= 0) break;
                $amountCents = min($amountCents, $cap);

                Payment::create([
                    'invoice_id'       => $invoice->id,
                    'student_id'       => $student->id,
                    'school_id'        => $schoolId,
                    'amount_cents'     => $amountCents,
                    'method'           => $p['method'] ?? 'cash',
                    'reference_number' => null,
                    'paid_at'          => $p['paid_at'],
                    'recorded_by'      => auth()->id(),
                    'notes'            => trim($p['notes'] ?? '') ?: 'Imehamishwa kutoka vitabuni',
                ]);
            }

            $invoice->syncStatus();
        }
    }

    private function nextMigrationNumber(): string
    {
        $year = date('Y');
        $last = Invoice::allSchools()
            ->where('invoice_number', 'like', "MIG-{$year}-%")
            ->max('invoice_number');
        $seq = $last ? ((int) substr($last, -6)) + 1 : 1;

        return sprintf('MIG-%s-%06d', $year, $seq);
    }

    private function generateInvoice(Student $student, array $data): void
    {
        $feeStructure = FeeStructure::where('school_id', $data['school_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('term_id', $data['term_id'])
            ->where('is_active', true)
            ->with('feeItems')
            ->first();

        if (! $feeStructure) {
            return; // No fee structure configured — skip silently
        }

        $totalCents = $feeStructure->totalCents();
        $openingBalance = (int) ($data['opening_balance_cents'] ?? 0);

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

        // Create invoice lines from fee structure items
        foreach ($feeStructure->feeItems as $item) {
            $invoice->lines()->create([
                'fee_item_id' => $item->id,
                'description' => $item->name,
                'amount_cents' => $item->amount_cents->cents(),
            ]);
        }

        // Apply discount if provided
        if (! empty($data['discount_type']) && ! empty($data['discount_amount_cents'])) {
            Discount::create([
                'student_id' => $student->id,
                'invoice_id' => $invoice->id,
                'type' => $data['discount_type'],
                'amount_cents' => (int) $data['discount_amount_cents'],
                'is_percentage' => false,
                'reason' => $data['discount_type'],
                'applied_by' => auth()->id(),
            ]);

            $invoice->discount_cents = (int) $data['discount_amount_cents'];
            $invoice->save();
        }

        $invoice->syncStatus();
    }
}
