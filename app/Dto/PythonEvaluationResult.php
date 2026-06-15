<?php

namespace App\Dto;

class PythonEvaluationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int|null $httpStatus,
        public readonly string|null $submissionId = null,
        public readonly string|null $transcript = null,
        public readonly float|null $audioDurationSeconds = null,
        public readonly float|null $recognizedDurationSeconds = null,
        public readonly array|null $speechRate = null,
        public readonly string|null $azureRequestId = null,
        public readonly string|null $azureSessionId = null,
        public readonly array|null $rawAzureResponse = null,
        public readonly string|null $errorType = null,
        public readonly bool $retryable = false,
        public readonly string|null $userAction = null,
        public readonly string|null $detail = null,
        public readonly array $diagnostic = [],
    ) {}

    public static function success(int $httpStatus, array $payload): self
    {
        return new self(
            success: true,
            httpStatus: $httpStatus,
            submissionId: is_string($payload['submission_id'] ?? null) ? $payload['submission_id'] : null,
            transcript: is_string($payload['transcript'] ?? null) ? $payload['transcript'] : null,
            audioDurationSeconds: self::nullableFloat($payload['audio_duration_seconds'] ?? null),
            recognizedDurationSeconds: self::nullableFloat($payload['recognized_duration_seconds'] ?? null),
            speechRate: is_array($payload['speech_rate'] ?? null) ? $payload['speech_rate'] : null,
            azureRequestId: is_string($payload['azure_request_id'] ?? null) ? $payload['azure_request_id'] : null,
            azureSessionId: is_string($payload['azure_session_id'] ?? null) ? $payload['azure_session_id'] : null,
            rawAzureResponse: is_array($payload['raw_azure_response'] ?? null) ? $payload['raw_azure_response'] : null,
        );
    }

    public static function failure(
        string $errorType,
        string $detail,
        int|null $httpStatus = null,
        bool $retryable = false,
        string|null $userAction = null,
        array $diagnostic = [],
    ): self {
        return new self(
            success: false,
            httpStatus: $httpStatus,
            errorType: $errorType,
            retryable: $retryable,
            userAction: $userAction,
            detail: $detail,
            diagnostic: $diagnostic,
        );
    }

    private static function nullableFloat(mixed $value): float|null
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
