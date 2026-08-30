<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's dashboard privacy lock. See the create_dashboard_locks_table
 * migration for why this is per-user rather than per-school.
 */
class DashboardLock extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'is_enabled', 'locked_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime', 'is_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->locked_at !== null;
    }

    /** False after a deliberate deactivate() — the code and hash stay stored. */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }
}
