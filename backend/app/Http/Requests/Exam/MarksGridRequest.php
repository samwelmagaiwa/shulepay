<?php

namespace App\Http\Requests\Exam;

use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;

/** Query parameters selecting the class and subject for the marks-entry grid. */
class MarksGridRequest extends ClassResultsRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where(fn ($q) => ExamScope::whereSchool($q))],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'subject_id.required' => 'Choose a subject.',
            'subject_id.integer' => 'The subject is not valid.',
            'subject_id.exists' => 'The selected subject does not exist.',
        ];
    }
}
