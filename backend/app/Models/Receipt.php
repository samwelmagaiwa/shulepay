<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receipt extends Model {
    protected $fillable = ['receipt_number','student_id','issued_at'];
    protected $casts = ['issued_at' => 'datetime'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function payment(): HasOne { return $this->hasOne(Payment::class); }
}
