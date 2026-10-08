<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolNotification extends Model
{
    protected $fillable = [
        'school_id', 'type', 'title', 'body', 'data',
        'sender_type', 'sender_id', 'recipient_role', 'is_read', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Absence alerts are worded in the language of whoever reads them. They used
     * to be stored as finished Swahili sentences, which stayed Swahili for a user
     * working in English. Older rows (no class_name in data) keep their stored text.
     */
    public function getTitleAttribute(?string $value): ?string
    {
        if ($this->type === 'absence_alert' && isset($this->data['class_name'], $this->data['absent_count'])) {
            return __(':count students were absent - :class', [
                'count' => $this->data['absent_count'],
                'class' => $this->data['class_name'],
            ]);
        }

        return $value;
    }

    public function getBodyAttribute(?string $value): ?string
    {
        if ($this->type === 'absence_alert' && isset($this->data['absent_names'], $this->data['date'])) {
            return __('Date :date: :names', [
                'date' => $this->data['date'],
                'names' => $this->data['absent_names'],
            ]);
        }

        return $value;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
