<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an audit log entry.
     *
     * @param  string      $action   e.g. 'refund_created', 'rollover_executed'
     * @param  Model|null  $subject  The primary model being acted upon
     * @param  array       $changes  ['before' => [...], 'after' => [...]] or any context array
     * @param  string|null $note     Free-text note
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        ?string $note = null
    ): void {
        $before = $changes['before'] ?? [];
        $after  = $changes['after']  ?? $changes;

        // If the caller passed a flat array (not before/after keyed), treat it as 'after'.
        if (isset($changes['before']) || isset($changes['after'])) {
            $before = $changes['before'] ?? [];
            $after  = $changes['after']  ?? [];
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_type' => $subject ? get_class($subject) : null,
            'model_id'   => $subject?->getKey(),
            'before'     => $before ?: null,
            'after'      => $after  ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
