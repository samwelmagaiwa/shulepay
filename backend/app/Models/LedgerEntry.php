<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = [
        'account_id', 'school_id', 'type', 'amount_cents', 'description',
        'reference_type', 'reference_id', 'created_by',
    ];
}
