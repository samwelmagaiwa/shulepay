<?php

namespace App\Http\Resources;

use App\Services\Budget\BudgetActuals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => $this->academicYear->name),
            'name' => $this->name,
            'status' => $this->status,
            // what was really spent, worked out from the approved expenses and refunds of the budget's year
            'summary' => $this->whenLoaded('items', fn () => collect(app(BudgetActuals::class)->summary($this->resource))->except('lines')->all()),
            'items' => $this->whenLoaded('items', fn () => app(BudgetActuals::class)->summary($this->resource)['lines']),
            'period' => $this->whenLoaded('academicYear', fn () => [$this->academicYear?->start_date?->toDateString(), $this->academicYear?->end_date?->toDateString()]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
