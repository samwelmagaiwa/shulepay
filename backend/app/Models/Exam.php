<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use BelongsToSchool;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_PUBLISHED];

    public const TYPES = ['test', 'midterm', 'terminal', 'annual', 'mock'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'name', 'type',
        'start_date', 'end_date', 'status', 'published_at', 'published_by', 'created_by', 'grading_snapshot', 'all_classes',
        'out_of', 'marks_deadline', 'next_term_opens', 'hold_for_fees', 'require_comments',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'published_at' => 'datetime',
        'grading_snapshot' => 'array',
        'all_classes' => 'boolean',
        'out_of' => 'integer',
        'marks_deadline' => 'date:Y-m-d',
        'next_term_opens' => 'date:Y-m-d',
        'hold_for_fees' => 'boolean',
        'require_comments' => 'boolean',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'all_classes' => true, 'out_of' => 100, 'hold_for_fees' => false, 'require_comments' => false];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** The classes taking this exam when it is not school-wide. */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_classes', 'exam_id', 'school_class_id');
    }

    public function coversClass(SchoolClass $class): bool
    {
        return $this->all_classes || $this->classes()->whereKey($class->id)->exists();
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    /** True once the marks deadline day is over (the deadline day itself is still open). */
    public function deadlinePassed(): bool
    {
        return $this->marks_deadline !== null && now()->startOfDay()->gt($this->marks_deadline);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
