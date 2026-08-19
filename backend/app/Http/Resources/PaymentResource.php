<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource {
    public function toArray($request): array {
        return [
            'id'               => $this->id,
            'invoice_id'       => $this->invoice_id,
            'student_id'       => $this->student_id,
            'amount_cents'     => $this->amount_cents->cents(),
            'method'           => $this->method->value,
            'method_label'     => $this->method->label(),
            'reference_number' => $this->reference_number,
            'paid_at'          => $this->paid_at?->toISOString(),
            'notes'            => $this->notes,
            'student'          => $this->whenLoaded('invoice', function () {
                $student    = $this->invoice->student;
                $enrollment = $student?->currentEnrollment;
                return [
                    'id'               => $student?->id,
                    'full_name'        => $student?->fullName(),
                    'admission_number' => $enrollment?->admission_number,
                    'school_class'     => $enrollment?->schoolClass
                        ? ['name' => $enrollment->schoolClass->name]
                        : null,
                    'school'           => $enrollment?->school
                        ? ['name' => $enrollment->school->name]
                        : ($this->invoice->school ? ['name' => $this->invoice->school->name] : null),
                    'guardians'        => $student?->guardians?->map(fn($g) => [
                        'name'         => $g->fullName(),
                        'phone'        => $g->phone,
                        'relationship' => $g->pivot?->relation ?? null,
                    ])->values(),
                ];
            }),
            'invoice'          => $this->whenLoaded('invoice', fn() => [
                'id'                 => $this->invoice->id,
                'invoice_number'     => $this->invoice->invoice_number,
                'term'               => $this->invoice->term?->name,
                'academic_year'      => $this->invoice->academicYear?->name,
                'school'             => $this->invoice->school?->name,
                'total_amount_cents' => $this->invoice->total_amount_cents->cents(),
                'paid_cents'         => $this->invoice->paidCents(),
                'balance_due_cents'  => $this->invoice->balanceDueCents(),
                'status'             => $this->invoice->status?->value,
            ]),
            'receipt'          => $this->whenLoaded('receipt', fn() => [
                'id'             => $this->receipt->id,
                'receipt_number' => $this->receipt->receipt_number,
                'issued_at'      => $this->receipt->issued_at?->toISOString(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
