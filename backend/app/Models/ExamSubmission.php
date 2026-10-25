<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A teacher has handed in the marks for one class and subject of an exam. */
class ExamSubmission extends Model
{
    protected $fillable = ['exam_id', 'school_class_id', 'subject_id', 'submitted_by', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];
}
