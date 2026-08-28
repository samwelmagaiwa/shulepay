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
    protected $fillable = ['user_id', 'code_hash', 'locked_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->locked_at !== null;
    }
}
