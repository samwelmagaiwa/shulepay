<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSubject extends Model
{
    protected $table = 'class_subjects';

    protected $fillable = ['school_class_id', 'subject_id', 'employee_id'];
}
