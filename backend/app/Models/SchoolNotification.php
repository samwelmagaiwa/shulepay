<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolNotification extends Model {
    protected $fillable = [
        'school_id', 'type', 'title', 'body', 'data',
        'sender_type', 'sender_id', 'recipient_role', 'is_read', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function school(): BelongsTo {
        return $this->belongsTo(School::class);
    }

    public function scopeUnread(Builder $query): Builder {
        return $query->where('is_read', false);
    }
}
