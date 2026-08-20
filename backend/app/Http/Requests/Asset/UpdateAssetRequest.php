<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $assetId = $this->route('asset')?->id;

        return [
            'asset_tag' => "sometimes|required|string|max:30|unique:assets,asset_tag,{$assetId}",
            'name' => 'sometimes|required|string|max:200',
            'category' => 'sometimes|required|in:furniture,technology,equipment,vehicles,buildings,sports,other',
            'school_id' => 'sometimes|required|exists:schools,id',
            'quantity' => 'nullable|integer|min:1',
            'serial_no' => 'nullable|string|max:100',
            'photo' => 'nullable|image|max:2048',
            'purchase_cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'supplier_name' => 'nullable|string|max:200',
            'invoice_no' => 'nullable|string|max:100',
            'funding_source' => 'nullable|in:fees,donation,grant',
            'depreciation_method' => 'nullable|in:straight_line,reducing_balance',
            'useful_life_years' => 'nullable|integer|min:1|max:100',
            'depreciation_rate' => 'nullable|numeric|min:0.01|max:100',
            'salvage_value' => 'nullable|numeric|min:0',
            'custodian' => 'nullable|string|max:200',
            'custodian_employee_id' => 'nullable|exists:employees,id',
            'location' => 'nullable|string|max:200',
            'condition' => 'nullable|in:excellent,good,fair,poor',
            'status' => 'nullable|in:in_use,under_repair,disposed,lost,written_off',
            'warranty_expiry' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
