<?php

namespace App\Http\Requests\Exam;

use Illuminate\Validation\Validator;

/**
 * Replace the whole grading scale. Each band is a grade and the lowest mark that
 * earns it; the scale must reach down to 0 so every mark has a grade.
 */
class UpdateGradingScaleRequest extends ExamFormRequest
{
    public function rules(): array
    {
        return [
            'bands' => ['required', 'array', 'min:2', 'max:15'],
            'bands.*.grade' => ['required', 'string', 'max:5', 'regex:/^[A-Za-z0-9+\-]+$/'],
            'bands.*.min_marks' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'bands.*.points' => ['nullable', 'integer', 'between:0,20'],
            'bands.*.remark' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'bands.required' => 'Add at least two grade bands.',
            'bands.min' => 'A grading scale needs at least two bands.',
            'bands.max' => 'A grading scale cannot have more than 15 bands.',
            'bands.*.grade.required' => 'Band :position: enter the grade (for example A).',
            'bands.*.grade.max' => 'Band :position: the grade may not be longer than 5 characters.',
            'bands.*.grade.regex' => 'Band :position: the grade may only contain letters, digits, + or -.',
            'bands.*.min_marks.required' => 'Band :position: enter the lowest mark for this grade.',
            'bands.*.min_marks.numeric' => 'Band :position: the lowest mark must be a number.',
            'bands.*.min_marks.between' => 'Band :position: the lowest mark must be between 0 and 100.',
            'bands.*.min_marks.decimal' => 'Band :position: use at most 2 decimal places.',
            'bands.*.points.integer' => 'Band :position: points must be a whole number.',
            'bands.*.points.between' => 'Band :position: points must be between 0 and 20.',
            'bands.*.remark.max' => 'Band :position: the remark may not be longer than 100 characters.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $bands = collect($this->input('bands', []));

            // Field-level rules already failed; cross-band checks would only add noise.
            if ($validator->errors()->isNotEmpty() || $bands->isEmpty()) {
                return;
            }

            $grades = $bands->map(fn ($band) => strtoupper(trim($band['grade'])));
            if ($grades->count() !== $grades->unique()->count()) {
                $validator->errors()->add('bands', 'Each grade may appear only once: '.$grades->duplicates()->unique()->implode(', ').' is repeated.');
            }

            $floors = $bands->map(fn ($band) => round((float) $band['min_marks'], 2));
            if ($floors->count() !== $floors->unique()->count()) {
                $validator->errors()->add('bands', 'Two bands start at the same mark ('.$floors->duplicates()->unique()->implode(', ').'). Each band needs its own starting mark.');
            }

            if (! $floors->contains(0.0)) {
                $validator->errors()->add('bands', 'The lowest band must start at 0 so every mark has a grade.');
            }
        }];
    }
}
