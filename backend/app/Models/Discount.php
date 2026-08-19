<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Casts\MoneyCast;

class Discount extends Model {
    protected $fillable = ['student_id','invoice_id','type','amount_cents','percentage','is_percentage','reason','applied_by','applied_at'];
    protected $casts = ['amount_cents' => MoneyCast::class, 'is_percentage' => 'boolean', 'percentage' => 'decimal:2'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function appliedBy(): BelongsTo { return $this->belongsTo(User::class, 'applied_by'); }

    public function computedCents(int $totalCents): int {
        if ($this->is_percentage) {
            return (int) round($totalCents * ($this->percentage / 100));
        }
        return $this->amount_cents->cents();
    }
}
