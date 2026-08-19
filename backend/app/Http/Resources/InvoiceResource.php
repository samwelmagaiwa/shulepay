<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->fullName(),
                'admission_number' => $this->student->currentEnrollment?->admission_number,
                'school_class' => $this->student->currentEnrollment?->schoolClass
                    ? ['id' => $this->student->currentEnrollment->schoolClass->id,
                        'name' => $this->student->currentEnrollment->schoolClass->name]
                    : null,
            ]),
            'term_id' => $this->term_id,
            'term' => $this->whenLoaded('term', fn () => [
                'id' => $this->term->id,
                'name' => $this->term->name,
                'number' => $this->term->number,
            ]),
            'academic_year_id' => $this->academic_year_id,
            'total_amount_cents' => $this->total_amount_cents->cents(),
            'discount_cents' => $this->discount_cents->cents(),
            'arrears_cents' => $this->arrears_cents->cents(),
            'paid_cents' => $this->paidCents(),
            'balance_due_cents' => $this->balanceDueCents(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'generated_at' => $this->generated_at?->toISOString(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'id' => $l->id,
                'description' => $l->description,
                'amount_cents' => $l->amount_cents->cents(),
            ])
            ),
            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
