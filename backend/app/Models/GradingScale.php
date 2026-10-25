<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of a school's grading scale. A band is defined by its lower bound
 * only; it runs up to the next band's lower bound.
 */
class GradingScale extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'grade', 'min_marks', 'points', 'remark'];

    protected $casts = [
        'min_marks' => 'float',
        'points' => 'integer',
    ];
}
