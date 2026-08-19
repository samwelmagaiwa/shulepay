<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $schoolId = $this->route('school')?->id;

        return [
            'name'                => ['sometimes', 'required', 'string', 'max:150'],
            'code'                => ['sometimes', 'required', 'string', 'max:10', 'alpha_num', 'uppercase', Rule::unique('schools', 'code')->ignore($schoolId)],
            'level'               => ['sometimes', 'required', Rule::in(['primary', 'secondary'])],
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
}
