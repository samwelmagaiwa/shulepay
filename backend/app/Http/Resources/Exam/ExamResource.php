<?php

namespace App\Http\Resources\Exam;

use App\Models\Exam;
use App\Services\Exam\ExamScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Exam */
class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'term_id' => $this->term_id,
            'term' => $this->whenLoaded('term', fn () => $this->term?->name),
            'academic_year' => $this->whenLoaded('term', fn () => $this->term?->academicYear?->name),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'out_of' => (int) ($this->out_of ?? 100),
            'marks_deadline' => $this->marks_deadline?->toDateString(),
            'next_term_opens' => $this->next_term_opens?->toDateString(),
            'hold_for_fees' => (bool) $this->hold_for_fees,
            'require_comments' => (bool) $this->require_comments,
            'all_classes' => (bool) $this->all_classes,
            'class_ids' => $this->whenLoaded('classes', fn () => $this->classes->pluck('id')->all()),
            'classes' => $this->whenLoaded('classes', fn () => $this->classes->map(fn ($c) => ExamScope::classLabel($c))->all()),
            'published_at' => $this->published_at?->toIso8601String(),
            'results_count' => $this->whenCounted('results'),
        ];
    }
}
