<?php

namespace App\Http\Requests\Refund;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;

        return [
            'invoice_id' => [
                'required', 'integer',
                "exists:invoices,id,school_id,{$schoolId}",
            ],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'in:cash,mpesa,bank'],
        ];
    }
}
