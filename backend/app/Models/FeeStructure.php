<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    protected $fillable = ['school_id', 'school_class_id', 'academic_year_id', 'term_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

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

    public function feeItems(): HasMany
    {
        return $this->hasMany(FeeItem::class)->orderBy('sort_order');
    }

    public function totalCents(): int
    {
        return $this->feeItems->sum(fn ($item) => $item->amount_cents->cents());
    }
}
