<?php

namespace App\Dto;

class PythonEvaluationHealthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int|null $httpStatus,
        public readonly string|null $status = null,
        public readonly string|null $service = null,
        public readonly string|null $errorType = null,
        public readonly string|null $detail = null,
        public readonly bool $retryable = false,
        public readonly array $diagnostic = [],
    ) {}

    public static function success(int $httpStatus, array $payload): self
    {
        return new self(
            success: true,
            httpStatus: $httpStatus,
            status: is_string($payload['status'] ?? null) ? $payload['status'] : null,
            service: is_string($payload['service'] ?? null) ? $payload['service'] : null,
        );
    }

    public static function failure(
        string $errorType,
        string $detail,
        int|null $httpStatus = null,
        bool $retryable = false,
        array $diagnostic = [],
    ): self {
        return new self(
            success: false,
            httpStatus: $httpStatus,
            errorType: $errorType,
            detail: $detail,
            retryable: $retryable,
            diagnostic: $diagnostic,
        );
    }
}
