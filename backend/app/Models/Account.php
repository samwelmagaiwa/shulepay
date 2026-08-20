<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'school_id', 'name', 'type', 'balance_cents', 'currency', 'is_active',
    ];
}
