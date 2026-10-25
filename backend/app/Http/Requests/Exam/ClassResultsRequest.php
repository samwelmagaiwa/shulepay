<?php

namespace App\Http\Requests\Exam;

use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;

/** Query parameters selecting a class for the results views. */
class ClassResultsRequest extends ExamFormRequest
{
    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where(fn ($q) => ExamScope::whereSchool($q))],
            // report cards only: print just these pupils of the class
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_class_id.required' => 'Choose a class.',
            'school_class_id.integer' => 'The class is not valid.',
            'school_class_id.exists' => 'The selected class does not exist.',
        ];
    }
}
