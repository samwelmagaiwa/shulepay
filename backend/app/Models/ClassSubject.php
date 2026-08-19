<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSubject extends Model
{
    protected $fillable = ['school_class_id', 'subject_id', 'employee_id'];
}
