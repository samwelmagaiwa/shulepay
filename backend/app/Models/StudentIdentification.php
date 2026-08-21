<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentIdentification extends Model
{
    protected $fillable = ['student_id', 'type', 'number', 'expires_at', 'is_primary'];

    protected $casts = ['expires_at' => 'date', 'is_primary' => 'boolean'];

    public static array $TYPES = [
        'nida'             => 'National ID (NIDA)',
        'driving_license'  => 'Driving License',
        'voter_id'         => 'Voter ID',
        'passport'         => 'Passport',
        'birth_certificate'=> 'Birth Certificate',
        'student_id'       => 'Student ID Card',
        'other'            => 'Other',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
