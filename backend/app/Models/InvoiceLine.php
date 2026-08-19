<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\MoneyCast;

class InvoiceLine extends Model {
    protected $fillable = ['invoice_id','fee_item_id','description','amount_cents'];
    protected $casts = ['amount_cents' => MoneyCast::class];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function feeItem(): BelongsTo { return $this->belongsTo(FeeItem::class); }
}
