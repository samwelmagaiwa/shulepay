<?php

namespace App\Http\Requests\Exam;

use App\Models\Exam;
use App\Models\Term;
use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create or update an exam (the route's {exam}, if any, is ignored for uniqueness). */
class ExamRequest extends ExamFormRequest
{
    public function rules(): array
    {
        // The controller takes the raw id (see ExamController::find), so the route
        // parameter is the id itself.
        $examId = $this->route('exam');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('exams', 'name')
                    ->where(fn ($query) => ExamScope::whereSchool($query)->where('term_id', $this->input('term_id')))
                    ->ignore($examId),
            ],
            'type' => ['required', Rule::in(Exam::TYPES)],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'all_classes' => ['required', 'boolean'],
            'class_ids' => ['exclude_if:all_classes,true', 'required', 'array', 'min:1'],
            'class_ids.*' => [
                'integer', 'distinct',
                Rule::exists('school_classes', 'id')->where(fn ($query) => ExamScope::whereSchool($query)),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'marks_deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'next_term_opens' => ['nullable', 'date'],
            'out_of' => ['nullable', 'integer', 'between:10,1000'],
            'hold_for_fees' => ['nullable', 'boolean'],
            'require_comments' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('exams.validation.name_required'),
            'name.max' => __('exams.validation.name_max'),
            'name.unique' => __('exams.validation.name_unique'),
            'type.required' => __('exams.validation.type_required'),
            'type.in' => __('exams.validation.type_in', ['types' => implode(', ', Exam::TYPES)]),
            'term_id.required' => __('exams.validation.term_required'),
            'term_id.integer' => __('exams.validation.term_invalid'),
            'term_id.exists' => __('exams.validation.term_invalid'),
            'all_classes.required' => __('exams.validation.all_classes_required'),
            'class_ids.required' => __('exams.validation.classes_required'),
            'class_ids.min' => __('exams.validation.classes_required'),
            'class_ids.*.exists' => __('exams.validation.class_invalid'),
            'class_ids.*.distinct' => __('exams.validation.class_distinct'),
            'start_date.date' => __('exams.validation.date_invalid'),
            'end_date.date' => __('exams.validation.date_invalid'),
            'end_date.after_or_equal' => __('exams.validation.end_before_start'),
            'marks_deadline.date' => __('exams.validation.date_invalid'),
            'marks_deadline.after_or_equal' => __('exams.validation.deadline_before'),
            'next_term_opens.date' => __('exams.validation.date_invalid'),
            'out_of.integer' => __('exams.validation.out_of'),
            'out_of.between' => __('exams.validation.out_of'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('term_id') || ! $this->filled('term_id')) {
                return;
            }

            $term = Term::with('academicYear')->find($this->input('term_id'));
            $schoolIds = ExamScope::schoolIds();

            // Terms carry no school_id of their own; they belong to a school through
            // their academic year. Reject a term from someone else's school.
            if ($term && $schoolIds && ! in_array((int) $term->academicYear?->school_id, $schoolIds, true)) {
                $validator->errors()->add('term_id', __('exams.validation.term_invalid'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        // Omitted means the classic behaviour: the exam is school-wide.
        $this->mergeIfMissing(['all_classes' => true]);

        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
