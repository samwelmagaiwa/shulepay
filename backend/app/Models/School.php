<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'name',
        'code',
        'slug',
        'level',
        'address',
        'region',
        'district',
        'ward',
        'phone',
        'email',
        'website',
        'registration_number',
        'established_year',
        'capacity',
        'owner_name',
        'motto',
        'logo',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'level' => SchoolLevel::class,
    ];

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function currentAcademicYear(): ?AcademicYear
    {
        return $this->academicYears()->where('is_current', true)->first();
    }

    public function currentTerm(): ?Term
    {
        $year = $this->currentAcademicYear();

        return $year ? $year->terms()->where('is_current', true)->first() : null;
    }

    /** Generate next admission number for this school.
     *  Primary:   PRM/{code}/{0001}/{year}
     *  Secondary: SEC/{code}/{0001}/{year}
     */
    public function nextAdmissionNumber(): string
    {
        $year = now()->year;
        $prefix = $this->level?->admissionPrefix() ?? 'PRM';
        $code = strtoupper($this->code);

        // The sequence is scoped to this school and year — deliberately NOT to the
        // school code. Matching on the code meant that renaming a school's code
        // restarted numbering at 0001 mid-year, because the old numbers no longer
        // matched the LIKE. Existing admission numbers are never rewritten (they are
        // printed on receipts and certificates), so the series simply continues.
        $seq = Enrollment::withoutGlobalScope('school')
            ->where('school_id', $this->id)
            ->where('admission_number', 'like', "%/{$year}")
            ->get(['admission_number'])
            ->map(fn ($e) => preg_match('#/(\d+)/'.$year.'$#', $e->admission_number, $m) ? (int) $m[1] : 0)
            ->max();

        return sprintf('%s/%s/%04d/%d', $prefix, $code, ((int) $seq) + 1, $year);
    }

    /** Return label suitable for class levels — "Darasa" or "Kidato" */
    public function classLabel(): string
    {
        // Delegate to the enum, which already covers the Swahili cases; the old
        // `=== SchoolLevel::Primary` comparison labelled a 'msingi' school "Kidato".
        return $this->level?->classPrefix() ?? 'Darasa';
    }
}
