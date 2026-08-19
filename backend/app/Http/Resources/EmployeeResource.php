<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school->name),
            'user_id' => $this->user_id,
            'staff_number' => $this->staff_number,
            'full_name' => $this->full_name,
            'role' => $this->role,
            'department' => $this->department,
            'basic_salary_cents' => $this->getRawOriginal('basic_salary_cents'),
            'hire_date' => $this->hire_date?->toDateString(),
            'status' => $this->status,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
