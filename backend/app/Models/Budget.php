<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model {
    use BelongsToSchool;

    protected $fillable = ['school_id', 'academic_year_id', 'name', 'status'];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany {
        return $this->hasMany(BudgetItem::class);
    }
}
