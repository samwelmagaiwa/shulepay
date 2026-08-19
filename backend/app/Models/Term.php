<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model {
    protected $fillable = ['academic_year_id', 'name', 'number', 'start_date', 'end_date', 'is_current'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function feeStructures(): HasMany { return $this->hasMany(FeeStructure::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
}
