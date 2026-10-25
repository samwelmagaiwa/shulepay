<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One change to a stored mark: who, when, from what to what. */
class ExamMarkChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'exam_id', 'student_id', 'subject_id', 'old_marks', 'new_marks', 'old_absent', 'new_absent',
        'changed_by', 'changed_by_name',
    ];

    protected $casts = ['old_marks' => 'float', 'new_marks' => 'float', 'old_absent' => 'boolean', 'new_absent' => 'boolean'];
}
