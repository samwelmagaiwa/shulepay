<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve branding so the frontend can switch instantly without a second API call.
        $branding = ($this->settings ?? [])['branding'] ?? [];
        $brandingLogoPath = $branding['logo_path'] ?? null;
        if ($brandingLogoPath) {
            $brandingLogoPath = preg_replace('#^public/#', '', $brandingLogoPath);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'level' => $this->level?->value,
            'level_label' => $this->level?->label(),
            'registration_number' => $this->registration_number,
            'established_year' => $this->established_year,
            'capacity' => $this->capacity,
            'owner_name' => $this->owner_name,
            'motto' => $this->motto,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'region' => $this->region,
            'district' => $this->district,
            'ward' => $this->ward,
            'logo_url' => $this->logo ? asset('storage/'.$this->logo) : null,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            // Resolved branding — merged name/tagline/logo ready for the header
            'branding' => [
                'app_name'    => $branding['app_name']    ?? $this->name,
                'app_tagline' => $branding['app_tagline'] ?? null,
                'logo_url'    => $brandingLogoPath ? '/storage/'.$brandingLogoPath : null,
            ],
            'students_count' => $this->whenCounted('enrollments'),
            'created_at' => $this->created_at?->toDateString(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
