<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school->name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category->name),
            'amount_cents' => $this->getRawOriginal('amount_cents'),
            'description' => $this->description,
            'vendor' => $this->vendor,
            'supplier_id' => $this->supplier_id,
            'supplier_bill_id' => $this->supplier_bill_id,
            'supplier_name' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'supplier_code' => $this->whenLoaded('supplier', fn () => $this->supplier?->code),
            'bill_number' => $this->whenLoaded('supplierBill', fn () => $this->supplierBill?->bill_number),
            'bill_status' => $this->whenLoaded('supplierBill', fn () => $this->supplierBill?->status),
            'payment_mode' => $this->payment_mode,
            'is_credit' => $this->payment_mode === 'credit',
            'receipt_reference' => $this->receipt_reference,
            'expense_date' => $this->expense_date?->toDateString(),
            'recorded_by' => $this->recorded_by,
            'recorder_name' => $this->whenLoaded('recorder', fn () => $this->recorder->name),
            'approved_by' => $this->approved_by,
            'approver_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
