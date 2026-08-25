<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray($request): array
    {
        $enrollment = $this->currentEnrollment;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'status' => $this->status,
            'sponsorship_type' => $this->sponsorship_type,
            'birth_certificate_no' => $this->birth_certificate_no,
            'nationality' => $this->nationality,
            'blood_group' => $this->blood_group,
            'allergies' => $this->allergies,
            'medical_conditions' => $this->medical_conditions,
            'address' => $this->address,
            'region' => $this->region,
            'district' => $this->district,
            'ward' => $this->ward,
            'street' => $this->street,
            'place' => $this->place,
            'religion' => $this->religion,
            'photo' => $this->photo ? url('storage/'.$this->photo) : null,
            'notes' => $this->notes,

            // Current enrollment fields (admission_number, class, school)
            'admission_number' => $enrollment?->admission_number,
            'school_id' => $enrollment?->school_id,
            'school_class_id' => $enrollment?->school_class_id,
            'school_class' => $enrollment ? [
                'id' => $enrollment->schoolClass?->id,
                'name' => $enrollment->schoolClass?->name,
            ] : null,
            'school' => $enrollment ? [
                'id' => $enrollment->school?->id,
                'name' => $enrollment->school?->name,
                'code' => $enrollment->school?->code,
                'level' => $enrollment->school?->level?->value,
            ] : null,
            'admitted_at' => $enrollment?->admitted_at?->format('Y-m-d'),

            // Full enrollment history (when loaded)
            'enrollments' => $this->whenLoaded(
                'enrollments',
                fn () => $this->enrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'school' => $e->school?->name,
                    'school_class' => $e->schoolClass?->name,
                    'admission_number' => $e->admission_number,
                    'status' => $e->status,
                    'admitted_at' => $e->admitted_at?->format('Y-m-d'),
                ])
            ),

            'identifications' => $this->whenLoaded(
                'identifications',
                fn () => $this->identifications->map(fn ($id) => [
                    'id' => $id->id,
                    'type' => $id->type,
                    'number' => $id->number,
                    'expires_at' => $id->expires_at?->format('Y-m-d'),
                    'is_primary' => $id->is_primary,
                ])
            ),

            'guardians' => $this->whenLoaded(
                'guardians',
                fn () => $this->guardians->map(fn ($g) => [
                    'id' => $g->id,
                    'full_name' => $g->fullName(),
                    'phone' => $g->phone,
                    'is_primary' => (bool) $g->pivot->is_primary,
                    'relation' => $g->pivot->relation,
                ])
            ),

            'invoices' => $this->whenLoaded(
                'invoices',
                fn () => InvoiceResource::collection($this->invoices)
            ),

            'outstanding_balance_cents' => $this->when(
                $this->relationLoaded('invoices'),
                fn () => $this->outstandingBalanceCents()
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
