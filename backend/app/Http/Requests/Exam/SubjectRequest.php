<?php

namespace App\Http\Requests\Exam;

use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;

/** Create or update a subject (the route's {subject}, if any, is ignored for uniqueness). */
class SubjectRequest extends ExamFormRequest
{
    public function rules(): array
    {
        // The controller takes the raw id, so the route parameter is the id itself.
        $subjectId = $this->route('subject');
        $unique = fn (string $column) => Rule::unique('subjects', $column)
            ->where(fn ($query) => ExamScope::whereSchool($query))
            ->ignore($subjectId);

        return [
            'name' => ['required', 'string', 'max:100', $unique('name')],
            'code' => ['nullable', 'string', 'max:20', $unique('code')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the subject name.',
            'name.max' => 'The subject name may not be longer than 100 characters.',
            'name.unique' => 'A subject with this name already exists.',
            'code.max' => 'The subject code may not be longer than 20 characters.',
            'code.unique' => 'Another subject already uses this code.',
            'is_active.boolean' => 'Active must be true or false.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'code' => filled($this->code) ? strtoupper(trim($this->code)) : null,
        ]);
    }
}
