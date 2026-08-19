<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id'            => $this->id,
            'school_id'     => $this->school_id,
            'school_name'   => $this->whenLoaded('school', fn() => $this->school?->name),
            'name'          => $this->name,
            'contact_name'  => $this->contact_name,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'address'       => $this->address,
            'balance_cents' => $this->getRawOriginal('balance_cents'),
            'created_at'    => $this->created_at?->toDateTimeString(),
            'updated_at'    => $this->updated_at?->toDateTimeString(),
        ];
    }
}
