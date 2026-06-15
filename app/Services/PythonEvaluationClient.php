<?php

namespace App\Services;

use App\Dto\PythonEvaluationHealthResult;
use App\Dto\PythonEvaluationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

class PythonEvaluationClient
{
    public function health(): PythonEvaluationHealthResult
    {
        try {
            $response = $this->baseRequest()->get($this->url('/health'));
        } catch (ConnectionException $exception) {
            return PythonEvaluationHealthResult::failure(
                errorType: $this->connectionErrorType($exception),
                detail: $exception->getMessage(),
                retryable: true,
            );
        }

        $payload = $this->jsonPayload($response);

        if ($payload === null) {
            return PythonEvaluationHealthResult::failure(
                errorType: 'invalid_json_response',
                detail: 'Python health response was not valid JSON.',
                httpStatus: $response->status(),
                retryable: $response->serverError(),
            );
        }

        if ($response->successful() && ($payload['status'] ?? null) === 'ok') {
            return PythonEvaluationHealthResult::success($response->status(), $payload);
        }

        return PythonEvaluationHealthResult::failure(
            errorType: 'python_health_unavailable',
            detail: is_string($payload['detail'] ?? null) ? $payload['detail'] : 'Python health check failed.',
            httpStatus: $response->status(),
            retryable: $response->serverError(),
            diagnostic: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $featureFlags
     */
    public function evaluate(
        string $audioFilePath,
        string $submissionId,
        int $questionId,
        int $expectedDuration,
        array $featureFlags = [],
    ): PythonEvaluationResult {
        if (! is_file($audioFilePath)) {
            return PythonEvaluationResult::failure(
                errorType: 'audio_file_missing',
                detail: 'Audio file for Python evaluation was not found.',
                retryable: false,
            );
        }

        try {
            $featureFlagsJson = json_encode($featureFlags, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return PythonEvaluationResult::failure(
                errorType: 'feature_flags_json_invalid',
                detail: $exception->getMessage(),
                retryable: false,
            );
        }

        $audioContents = @file_get_contents($audioFilePath);

        if ($audioContents === false) {
            return PythonEvaluationResult::failure(
                errorType: 'audio_file_unreadable',
                detail: 'Audio file for Python evaluation could not be read.',
                retryable: false,
            );
        }

        try {
            $response = $this->baseRequest()
                ->attach('audio_file', $audioContents, basename($audioFilePath))
                ->post($this->url('/evaluate'), [
                    'submission_id' => $submissionId,
                    'question_id' => (string) $questionId,
                    'expected_duration' => (string) $expectedDuration,
                    'feature_flags' => $featureFlagsJson,
                ]);
        } catch (ConnectionException $exception) {
            return PythonEvaluationResult::failure(
                errorType: $this->connectionErrorType($exception),
                detail: $exception->getMessage(),
                retryable: true,
            );
        }

        return $this->evaluationResultFromResponse($response);
    }

    private function baseRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout((float) config('services.python_evaluation.connect_timeout', 5))
            ->timeout((float) config('services.python_evaluation.read_timeout', 120))
            ->when(
                filled(config('services.python_evaluation.internal_token')),
                fn ($request) => $request->withHeader(
                    'X-Internal-Token',
                    (string) config('services.python_evaluation.internal_token'),
                ),
            );
    }

    private function evaluationResultFromResponse(Response $response): PythonEvaluationResult
    {
        $payload = $this->jsonPayload($response);

        if ($payload === null) {
            return PythonEvaluationResult::failure(
                errorType: 'invalid_json_response',
                detail: 'Python evaluation response was not valid JSON.',
                httpStatus: $response->status(),
                retryable: $response->serverError(),
            );
        }

        if ($response->successful() && ($payload['status'] ?? null) === 'success') {
            return PythonEvaluationResult::success($response->status(), $payload);
        }

        if ($response->status() === 401) {
            return PythonEvaluationResult::failure(
                errorType: 'internal_token_invalid',
                detail: is_string($payload['detail'] ?? null) ? $payload['detail'] : 'Python internal token was rejected.',
                httpStatus: 401,
                retryable: false,
                diagnostic: $payload,
            );
        }

        if (($payload['status'] ?? null) === 'error') {
            return PythonEvaluationResult::failure(
                errorType: is_string($payload['error_type'] ?? null) ? $payload['error_type'] : 'python_evaluation_error',
                detail: is_string($payload['detail'] ?? null) ? $payload['detail'] : 'Python evaluation failed.',
                httpStatus: $response->status(),
                retryable: (bool) ($payload['retryable'] ?? $response->serverError()),
                userAction: is_string($payload['user_action'] ?? null) ? $payload['user_action'] : null,
                diagnostic: is_array($payload['diagnostic'] ?? null) ? $payload['diagnostic'] : [],
            );
        }

        return PythonEvaluationResult::failure(
            errorType: 'unexpected_python_response',
            detail: 'Python evaluation response did not match the expected contract.',
            httpStatus: $response->status(),
            retryable: $response->serverError(),
            diagnostic: $payload,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonPayload(Response $response): array|null
    {
        try {
            $payload = $response->json();
        } catch (\Throwable) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.python_evaluation.base_url'), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function connectionErrorType(ConnectionException $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'connect') && str_contains($message, 'timed out')) {
            return 'python_connect_timeout';
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return 'python_read_timeout';
        }

        return 'python_connection_failed';
    }
}
