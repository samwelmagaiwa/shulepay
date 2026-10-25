<?php

namespace App\Http\Requests\Exam;

use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;

/** Replace the set of subjects a class sits. An empty list clears the class. */
class SyncClassSubjectsRequest extends ExamFormRequest
{
    public function rules(): array
    {
        return [
            'subject_ids' => ['present', 'array', 'max:60'],
            'subject_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('subjects', 'id')->where(fn ($query) => ExamScope::whereSchool($query)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'subject_ids.present' => 'Send the list of subjects for this class (it may be empty).',
            'subject_ids.array' => 'Subjects must be sent as a list.',
            'subject_ids.max' => 'A class cannot have more than 60 subjects.',
            'subject_ids.*.integer' => 'Each subject must be identified by its id.',
            'subject_ids.*.distinct' => 'A subject was selected more than once.',
            'subject_ids.*.exists' => 'One of the selected subjects does not exist.',
        ];
    }
}
