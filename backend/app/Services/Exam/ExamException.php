<?php

namespace App\Services\Exam;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business-rule failure in the exam module (wrong workflow state, incomplete
 * marks, ...). Renders itself as a structured JSON error so the UI can show a
 * clear message and, where relevant, exactly what needs fixing.
 */
class ExamException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $status = 422,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function state(string $message, string $code = 'EXAM_INVALID_STATE'): self
    {
        return new self($message, $code, 409);
    }

    public static function invalid(string $message, string $code, array $details = []): self
    {
        return new self($message, $code, 422, $details);
    }

    public static function forbidden(string $message, string $code): self
    {
        return new self($message, $code, 403);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details ?: null,
        ], fn ($v) => $v !== null), $this->status);
    }
}
