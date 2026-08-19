<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_number),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->fullName(),
            ]),
            'amount_cents' => $this->amount_cents->cents(),
            'reason' => $this->reason,
            'method' => $this->method,
            'refunded_by' => $this->whenLoaded('refundedBy', fn () => $this->refundedBy?->name),
            'refunded_at' => $this->refunded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
