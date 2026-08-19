<?php

namespace App\Http\Resources;

use App\Models\InstallmentPlan;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray($request): array
    {
        $totalInstallments = $this->totalSiblingCount();
        $installmentsPaid = $this->paidSiblingCount();

        // next_due_date: earliest due_date among unpaid siblings
        $nextDueDate = InstallmentPlan::where('invoice_id', $this->invoice_id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->value('due_date');

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_number),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->fullName(),
            ]),
            'installment_number' => $this->installment_number,
            'total_installments' => $totalInstallments,
            'installments_paid' => $installmentsPaid,
            'installment_amount_cents' => $this->amount_cents->cents(),
            'paid_amount_cents' => $this->paid_amount_cents->cents(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'next_due_date' => $nextDueDate instanceof Carbon
                                            ? $nextDueDate->format('Y-m-d')
                                            : ($nextDueDate ? Carbon::parse($nextDueDate)->format('Y-m-d') : null),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
