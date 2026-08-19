<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model {
    protected $fillable = ['first_name', 'last_name', 'phone', 'email', 'address', 'national_id', 'user_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function students(): BelongsToMany {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['relation', 'is_primary', 'receives_sms']);
    }
    public function fullName(): string { return "{$this->first_name} {$this->last_name}"; }
}
