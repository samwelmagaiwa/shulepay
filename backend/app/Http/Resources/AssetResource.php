<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                             => $this->id,
            'asset_tag'                      => $this->asset_tag,
            'name'                           => $this->name,
            'category'                       => $this->category,
            'school_id'                      => $this->school_id,
            'school'                         => $this->whenLoaded('school', fn() => ['id' => $this->school->id, 'name' => $this->school->name]),
            'school_name'                    => $this->whenLoaded('school', fn() => $this->school->name),
            'quantity'                       => $this->quantity ?? 1,
            'serial_no'                      => $this->serial_no,
            // Legacy field
            'serial_number'                  => $this->serial_number,
            'photo_url'                      => $this->photo ? Storage::url($this->photo) : null,
            // Money fields — return raw integer cents
            'purchase_cost_cents'            => (int) $this->getRawOriginal('purchase_cost_cents'),
            // Legacy cost field
            'cost_cents'                     => (int) $this->getRawOriginal('cost_cents'),
            'purchase_date'                  => $this->purchase_date?->toDateString(),
            'supplier_name'                  => $this->supplier_name,
            'invoice_no'                     => $this->invoice_no,
            'funding_source'                 => $this->funding_source,
            'depreciation_method'            => $this->depreciation_method,
            'useful_life_years'              => $this->useful_life_years,
            'depreciation_rate'              => $this->depreciation_rate,
            'salvage_value_cents'            => (int) $this->getRawOriginal('salvage_value_cents'),
            'current_value_cents'            => (int) $this->getRawOriginal('current_value_cents'),
            'accumulated_depreciation_cents' => (int) $this->getRawOriginal('accumulated_depreciation_cents'),
            'custodian'                      => $this->custodian,
            'location'                       => $this->location,
            'condition'                      => $this->condition,
            'status'                         => $this->status ?? 'in_use',
            'warranty_expiry'                => $this->warranty_expiry?->toDateString(),
            'disposal_date'                  => $this->disposal_date?->toDateString(),
            'disposal_value_cents'           => (int) $this->getRawOriginal('disposal_value_cents'),
            'disposal_reason'                => $this->disposal_reason,
            'registered_by'                  => $this->whenLoaded('registeredBy', fn() => $this->registeredBy?->name),
            'registered_at'                  => $this->registered_at?->toDateTimeString(),
            'notes'                          => $this->notes,
            'created_at'                     => $this->created_at?->toDateTimeString(),
            'updated_at'                     => $this->updated_at?->toDateTimeString(),
        ];
    }
}
