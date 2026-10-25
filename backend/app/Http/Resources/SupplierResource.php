<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'code' => $this->code,
            'status' => $this->status,
            'name' => $this->name,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'key' => $this->category->key,
                'label' => $this->category->label(),
            ] : null),
            'supplier_category_id' => $this->supplier_category_id,
            'contact_name' => $this->contact_name,
            'contact_title' => $this->contact_title,
            'phone' => $this->phone,
            'alt_phone' => $this->alt_phone,
            'email' => $this->email,
            'address' => $this->address,
            'region' => $this->region,
            'district' => $this->district,
            'tin' => $this->tin,
            'vrn' => $this->vrn,
            'business_reg_no' => $this->business_reg_no,
            'vat_registered' => (bool) $this->vat_registered,
            'wht_applicable' => (bool) $this->wht_applicable,
            'wht_type' => $this->wht_type,
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'mobile_money_provider' => $this->mobile_money_provider,
            'mobile_money_number' => $this->mobile_money_number,
            'mobile_money_name' => $this->mobile_money_name,
            'preferred_method' => $this->preferred_method,
            'payment_terms_days' => $this->payment_terms_days,
            'credit_limit_cents' => (int) $this->credit_limit_cents,
            'opening_balance_cents' => (int) $this->opening_balance_cents,
            'balance_cents' => (int) $this->getRawOriginal('balance_cents'),
            'overdue_cents' => $this->when(isset($this->resource->overdue_cents), fn () => (int) $this->resource->overdue_cents),
            'rating' => $this->rating,
            'notes' => $this->notes,
            'deactivated_at' => $this->deactivated_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
