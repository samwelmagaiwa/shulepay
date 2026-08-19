<?php
namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest {
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array {
        return [
            'invoice_id'       => 'required|exists:invoices,id',
            'amount_cents'     => 'required|integer|min:1',
            'method'           => 'required|in:cash,mpesa,bank,cheque',
            'reference_number' => 'nullable|string|max:100',
            'paid_at'          => 'nullable|date',
            'notes'            => 'nullable|string',
        ];
    }
}
