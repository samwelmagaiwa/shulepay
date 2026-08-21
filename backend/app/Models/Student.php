<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    // Students are identity-only. No school_id or school_class_id here.
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'status',
        'notes',
        'birth_certificate_no',
        'nationality',
        'religion',
        'photo',
        'blood_group',
        'allergies',
        'medical_conditions',
        'address',
        'region',
        'district',
        'ward',
        'street',
        'place',
    ];

    protected $casts = ['date_of_birth' => 'date'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Most recent active enrollment (use for admission_number, class, school). */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->where('status', 'active')
            ->latestOfMany('admitted_at');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relation', 'is_primary', 'receives_sms']);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function outstandingBalanceCents(): int
    {
        return $this->invoices->sum(fn (Invoice $inv) => $inv->balanceDueCents());
    }
}
