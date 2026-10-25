<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamComment extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'teacher_comment', 'head_comment', 'entered_by'];
}
