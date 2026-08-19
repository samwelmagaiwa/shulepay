<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = ['school_id', 'academic_year_id', 'term_id', 'name', 'type', 'start_date', 'end_date'];
}
