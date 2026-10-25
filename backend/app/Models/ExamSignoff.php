<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A class's comments for an exam signed off: stage "teacher" (class teacher) or "head" (head / academic coordinator). */
class ExamSignoff extends Model
{
    public const TEACHER = 'teacher';

    public const HEAD = 'head';

    protected $fillable = ['exam_id', 'school_class_id', 'stage', 'signed_by', 'signed_by_name', 'signed_at'];

    protected $casts = ['signed_at' => 'datetime'];
}
