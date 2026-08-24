<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDraft extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // User & School
        'user_id', 'school_id',

        // Step 1: Identity
        'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
        'birth_certificate_no', 'nationality', 'photo', 'blood_group', 'allergies',
        'medical_conditions', 'address', 'religion', 'region', 'district', 'ward', 'street',

        // Step 2: Enrollment
        'school_class_id', 'academic_year_id', 'term_id', 'enrollment_date',
        'previous_school', 'status', 'notes',

        // Step 3: Identifications
        'identifications',

        // Step 4: Guardians
        'guardians',

        // Step 5: Financial
        'total_tuition_fee_cents', 'discount_type', 'discount_amount_cents',
        'opening_balance_cents', 'generate_first_invoice',

        // Step 6: Payment History
        'is_existing_student', 'migration_mode', 'payment_history',
        'lumpsum_total_charged_cents', 'lumpsum_total_paid_cents',

        // Tracking
        'current_step', 'last_accessed_at',
    ];

    protected $casts = [
        'identifications' => 'array',
        'guardians' => 'array',
        'payment_history' => 'array',
        'date_of_birth' => 'date',
        'enrollment_date' => 'date',
        'last_accessed_at' => 'datetime',
        'is_existing_student' => 'boolean',
        'generate_first_invoice' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
