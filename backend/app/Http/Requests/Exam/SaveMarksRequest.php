<?php

namespace App\Http\Requests\Exam;

use App\Models\Exam;
use App\Services\Exam\ExamScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A batch of marks for one class and one subject.
 *
 * A blank mark that is not flagged absent clears that student's result, so the
 * payload can always be the whole grid as the teacher sees it.
 */
class SaveMarksRequest extends ExamFormRequest
{
    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where(fn ($q) => ExamScope::whereSchool($q))],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where(fn ($q) => ExamScope::whereSchool($q))],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.student_id' => ['required', 'integer'],
            'entries.*.marks' => ['nullable', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'entries.*.absent' => ['nullable', 'boolean'],
            'entries.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_class_id.required' => __('exams.validation.class_required'),
            'school_class_id.exists' => __('exams.validation.class_missing'),
            'subject_id.required' => __('exams.validation.subject_required'),
            'subject_id.exists' => __('exams.validation.subject_invalid'),
            'entries.required' => __('exams.validation.no_marks'),
            'entries.min' => __('exams.validation.no_marks'),
            'entries.max' => __('exams.validation.entries_max'),
            'entries.*.student_id.required' => __('exams.validation.student_missing'),
            'entries.*.student_id.integer' => __('exams.validation.student_missing'),
            'entries.*.marks.numeric' => __('exams.validation.marks_numeric'),
            'entries.*.marks.min' => __('exams.validation.marks_negative'),
            'entries.*.marks.max' => __('exams.validation.marks_numeric'),
            'entries.*.marks.decimal' => __('exams.validation.marks_decimals'),
            'entries.*.absent.boolean' => __('exams.validation.absent_boolean'),
            'entries.*.remarks.max' => __('exams.validation.remark_max'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            // Marks are out of the exam's total, which can be more or less than 100.
            $max = (int) (Exam::query()->find($this->route('exam'))?->out_of ?? 100);
            foreach ($this->input('entries', []) as $index => $entry) {
                $raw = $entry['marks'] ?? null;
                if (is_numeric($raw) && (float) $raw > $max && ! filter_var($entry['absent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $validator->errors()->add("entries.{$index}.marks", __('exams.err.marks_over', ['position' => $index + 1, 'max' => $max]));
                }
                if (filter_var($entry['absent'] ?? false, FILTER_VALIDATE_BOOLEAN) && filled($entry['marks'] ?? null)) {
                    $validator->errors()->add(
                        "entries.{$index}.marks",
                        __('exams.validation.absent_with_marks', ['position' => $index + 1]),
                    );
                }
            }
        }];
    }
}
