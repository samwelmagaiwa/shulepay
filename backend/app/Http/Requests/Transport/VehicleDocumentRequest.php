<?php

namespace App\Http\Requests\Transport;

use App\Models\VehicleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add or update a vehicle document. Authorization is done by the route's role middleware. */
class VehicleDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(VehicleDocument::TYPES)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'cost_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Choose the document type.',
            'type.in' => 'The document type must be one of: '.implode(', ', VehicleDocument::TYPES).'.',
            'expiry_date.required' => 'Enter the date the document expires.',
            'expiry_date.date' => 'The expiry date is not a valid date.',
            'expiry_date.after_or_equal' => 'The expiry date cannot be before the issue date.',
            'issue_date.date' => 'The issue date is not a valid date.',
            'cost_cents.min' => 'The cost cannot be negative.',
        ];
    }
}
