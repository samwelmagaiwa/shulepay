<?php

namespace App\Http\Requests\Exam;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for every exam-module request.
 *
 * Authorisation is done by route middleware (roles + plan), so requests only
 * validate. Failures come back in one predictable shape — a headline message and
 * per-field errors — that the UI shows in its notification card.
 */
abstract class ExamFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => __('messages.please_correct_the_following_and_try_again'),
            'code' => 'VALIDATION_FAILED',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
