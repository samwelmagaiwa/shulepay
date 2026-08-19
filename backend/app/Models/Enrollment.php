<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model {
    protected $fillable = [
        'student_id', 'school_id', 'school_class_id', 'academic_year_id',
        'term_id', 'admission_number', 'status', 'admitted_at', 'previous_school',
    ];
    protected $casts = ['admitted_at' => 'date'];

    public function student(): BelongsTo     { return $this->belongsTo(Student::class); }
    public function school(): BelongsTo      { return $this->belongsTo(School::class); }
    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function term(): BelongsTo          { return $this->belongsTo(Term::class); }
}
