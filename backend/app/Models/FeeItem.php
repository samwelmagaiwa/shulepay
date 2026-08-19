<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\MoneyCast;

class FeeItem extends Model {
    protected $fillable = ['fee_structure_id','name','category','amount_cents','is_optional','sort_order'];
    protected $casts = ['amount_cents' => MoneyCast::class, 'is_optional' => 'boolean'];

    public function feeStructure(): BelongsTo { return $this->belongsTo(FeeStructure::class); }
}
