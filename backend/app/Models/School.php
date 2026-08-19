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
        $prefix = $this->level === SchoolLevel::Secondary ? 'SEC' : 'PRM';
        $code = strtoupper($this->code);

        // Match numbers generated with the new format for this school/prefix/year
        $last = Enrollment::withoutGlobalScope('school')
            ->where('school_id', $this->id)
            ->where('admission_number', 'like', "{$prefix}/{$code}/%/{$year}")
            ->max('admission_number');

        if ($last && preg_match('/\/(\d+)\/' . $year . '$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s/%s/%04d/%d', $prefix, $code, $seq, $year);
    }

    /** Return label suitable for class levels — "Darasa" or "Kidato" */
    public function classLabel(): string
    {
        return $this->level === SchoolLevel::Primary ? 'Darasa' : 'Kidato';
    }
}
