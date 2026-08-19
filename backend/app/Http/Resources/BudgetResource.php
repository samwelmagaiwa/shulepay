<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id'               => $this->id,
            'school_id'        => $this->school_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year'    => $this->whenLoaded('academicYear', fn() => $this->academicYear->name),
            'name'             => $this->name,
            'status'           => $this->status,
            'items'            => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id'            => $item->id,
                'category'      => $item->category,
                'description'   => $item->description,
                'planned_cents' => $item->getRawOriginal('planned_cents'),
                'actual_cents'  => $item->getRawOriginal('actual_cents'),
            ])),
            'created_at'       => $this->created_at?->toDateTimeString(),
            'updated_at'       => $this->updated_at?->toDateTimeString(),
        ];
    }
}
