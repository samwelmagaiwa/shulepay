<?php

namespace App\Http\Requests\Transport;

use Illuminate\Foundation\Http\FormRequest;

/** Record or correct a refuel. Authorization is done by the route's role middleware. */
class FuelLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fuelled_on' => ['required', 'date', 'before_or_equal:today'],
            'litres' => ['required', 'numeric', 'gt:0', 'max:9999.99'],
            'cost_cents' => ['required', 'integer', 'min:0'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'full_tank' => ['nullable', 'boolean'],
            'station' => ['nullable', 'string', 'max:150'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fuelled_on.required' => 'Enter the date of the refuel.',
            'fuelled_on.before_or_equal' => 'The refuel date cannot be in the future.',
            'litres.required' => 'Enter how many litres were put in.',
            'litres.gt' => 'The litres must be more than zero.',
            'litres.max' => 'That is more litres than any tank holds — check the figure.',
            'cost_cents.required' => 'Enter what the fuel cost.',
            'cost_cents.min' => 'The cost cannot be negative.',
            'odometer_km.required' => 'Enter the odometer reading in km.',
            'odometer_km.min' => 'The odometer reading cannot be negative.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Most fills are full tanks; the form only needs to say when one is not.
        $this->mergeIfMissing(['full_tank' => true]);
    }
}
