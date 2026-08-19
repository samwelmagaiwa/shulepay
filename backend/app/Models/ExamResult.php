<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'subject_id', 'marks', 'grade', 'remarks'];
}
