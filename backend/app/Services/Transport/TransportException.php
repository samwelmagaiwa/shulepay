<?php

namespace App\Services\Transport;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business-rule failure in the transport module. Renders itself as a structured
 * JSON error so the UI can show a clear message.
 */
class TransportException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, private readonly string $errorCode, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function invalid(string $message, string $code, array $details = []): self
    {
        return new self($message, $code, $details);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details ?: null,
        ], fn ($v) => $v !== null), 422);
    }
}
