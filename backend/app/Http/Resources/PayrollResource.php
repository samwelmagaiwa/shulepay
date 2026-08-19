<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school->name),
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->full_name),
            'staff_number' => $this->whenLoaded('employee', fn () => $this->employee->staff_number),
            'month' => $this->month,
            'year' => $this->year,
            'basic_salary_cents' => $this->getRawOriginal('basic_salary_cents'),
            'allowances_cents' => $this->getRawOriginal('allowances_cents'),
            'deductions_cents' => $this->getRawOriginal('deductions_cents'),
            'net_salary_cents' => $this->getRawOriginal('net_salary_cents'),
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
