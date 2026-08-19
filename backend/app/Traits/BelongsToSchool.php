<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSchool {
    protected static function bootBelongsToSchool(): void {
        // Auto-scope queries to active school when bound in container
        static::addGlobalScope('school', function (Builder $q) {
            $school = app()->bound('active_school') ? app('active_school') : null;
            if ($school) {
                $q->where($q->getModel()->getTable() . '.school_id', $school->id);
            }
        });

        // Auto-set school_id on new records
        static::creating(function ($model) {
            $school = app()->bound('active_school') ? app('active_school') : null;
            if (empty($model->school_id) && $school) {
                $model->school_id = $school->id;
            }
        });
    }

    /** Remove school scope — used for "Shule Zote" cross-school aggregation. */
    public static function allSchools(): Builder {
        return static::withoutGlobalScope('school');
    }
}
