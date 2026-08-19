<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:150'],
            'code'                => ['required', 'string', 'max:10', 'alpha_num', 'uppercase', 'unique:schools,code'],
            'level'               => ['required', Rule::in(['primary', 'secondary'])],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'established_year'    => ['nullable', 'integer', 'min:1800', 'max:' . date('Y')],
            'capacity'            => ['nullable', 'integer', 'min:1', 'max:99999'],
            'owner_name'          => ['nullable', 'string', 'max:100'],
            'motto'               => ['nullable', 'string', 'max:200'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'email'               => ['nullable', 'email', 'max:100'],
            'website'             => ['nullable', 'url', 'max:200'],
            'address'             => ['nullable', 'string', 'max:255'],
            'region'              => ['nullable', 'string', 'max:100'],
            'district'            => ['nullable', 'string', 'max:100'],
            'ward'                => ['nullable', 'string', 'max:100'],
            'logo'                => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'is_active'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'        => 'School code already taken. Choose a different abbreviation.',
            'code.alpha_num'     => 'School code must contain only letters and numbers.',
            'code.uppercase'     => 'School code must be uppercase (e.g. MSG, KGSS).',
            'logo.max'           => 'Logo file must be 2 MB or smaller.',
        ];
    }
}
