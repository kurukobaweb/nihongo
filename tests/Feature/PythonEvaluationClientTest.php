<?php

namespace Tests\Feature;

use App\Services\PythonEvaluationClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PythonEvaluationClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.python_evaluation.base_url', 'http://python-evaluation.test');
        config()->set('services.python_evaluation.internal_token', 'test-internal-token');
        config()->set('services.python_evaluation.connect_timeout', 5);
        config()->set('services.python_evaluation.read_timeout', 120);
    }

    public function test_health_success_response_is_parsed(): void
    {
        Http::fake([
            'python-evaluation.test/health' => Http::response([
                'status' => 'ok',
                'service' => 'speech-evaluation',
            ]),
        ]);

        $result = app(PythonEvaluationClient::class)->health();

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatus);
        $this->assertSame('ok', $result->status);
        $this->assertSame('speech-evaluation', $result->service);
    }

    public function test_evaluate_success_response_is_parsed_and_internal_token_is_sent(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response($this->successPayload()),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate(
            audioFilePath: $this->audioPath(),
            submissionId: '00000000-0000-0000-0000-000000000001',
            questionId: 10,
            expectedDuration: 60,
            featureFlags: [
                'pronunciation_assessment' => false,
                'fluency_assessment' => false,
            ],
        );

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->httpStatus);
        $this->assertSame('00000000-0000-0000-0000-000000000001', $result->submissionId);
        $this->assertSame('日本語を練習しています。', $result->transcript);
        $this->assertSame(6.5, $result->audioDurationSeconds);
        $this->assertSame(5.0, $result->recognizedDurationSeconds);
        $this->assertSame(['characters_per_minute' => 180], $result->speechRate);
        $this->assertSame('request-1', $result->azureRequestId);
        $this->assertSame('session-1', $result->azureSessionId);

        Http::assertSent(function (Request $request) {
            $body = $request->body();

            return $request->url() === 'http://python-evaluation.test/evaluate'
                && $request->hasHeader('X-Internal-Token', 'test-internal-token')
                && str_contains($body, 'name="submission_id"')
                && str_contains($body, '00000000-0000-0000-0000-000000000001')
                && str_contains($body, 'name="question_id"')
                && str_contains($body, 'name="expected_duration"')
                && str_contains($body, 'name="feature_flags"')
                && str_contains($body, '{"pronunciation_assessment":false,"fluency_assessment":false}');
        });
    }

    public function test_evaluate_parses_422_audio_conversion_failed(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response($this->errorPayload(
                errorType: 'audio_conversion_failed',
                detail: 'Audio conversion failed',
                retryable: false,
                userAction: 'rerecord',
            ), 422),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->httpStatus);
        $this->assertSame('audio_conversion_failed', $result->errorType);
        $this->assertFalse($result->retryable);
        $this->assertSame('rerecord', $result->userAction);
    }

    public function test_evaluate_parses_422_speech_unrecognized(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response($this->errorPayload(
                errorType: 'speech_unrecognized',
                detail: 'Speech could not be recognized',
                retryable: false,
                userAction: 'rerecord',
                diagnostic: ['category' => 'speech_unrecognized'],
            ), 422),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame('speech_unrecognized', $result->errorType);
        $this->assertFalse($result->retryable);
        $this->assertSame(['category' => 'speech_unrecognized'], $result->diagnostic);
    }

    public function test_evaluate_parses_500_azure_configuration_unavailable(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response($this->errorPayload(
                errorType: 'azure_configuration_unavailable',
                detail: 'Azure Speech configuration is not available',
                retryable: false,
                userAction: 'contact_admin',
            ), 500),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame(500, $result->httpStatus);
        $this->assertSame('azure_configuration_unavailable', $result->errorType);
        $this->assertFalse($result->retryable);
    }

    public function test_evaluate_parses_503_azure_service_unavailable_as_retryable(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response($this->errorPayload(
                errorType: 'azure_service_unavailable',
                detail: 'Azure Speech SDK error',
                retryable: true,
                userAction: 'retry_later',
            ), 503),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame(503, $result->httpStatus);
        $this->assertSame('azure_service_unavailable', $result->errorType);
        $this->assertTrue($result->retryable);
        $this->assertSame('retry_later', $result->userAction);
    }

    public function test_evaluate_parses_401_as_internal_token_error(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response([
                'detail' => 'Invalid internal token',
            ], 401),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame(401, $result->httpStatus);
        $this->assertSame('internal_token_invalid', $result->errorType);
        $this->assertFalse($result->retryable);
    }

    public function test_evaluate_parses_invalid_json_response(): void
    {
        Http::fake([
            'python-evaluation.test/evaluate' => Http::response('not-json', 500),
        ]);

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertSame(500, $result->httpStatus);
        $this->assertSame('invalid_json_response', $result->errorType);
        $this->assertTrue($result->retryable);
    }

    public function test_evaluate_parses_connection_failure_as_client_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertNull($result->httpStatus);
        $this->assertSame('python_connection_failed', $result->errorType);
        $this->assertTrue($result->retryable);
    }

    public function test_evaluate_parses_timeout_as_client_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $result = app(PythonEvaluationClient::class)->evaluate($this->audioPath(), 'sub-1', 10, 60);

        $this->assertFalse($result->success);
        $this->assertNull($result->httpStatus);
        $this->assertSame('python_read_timeout', $result->errorType);
        $this->assertTrue($result->retryable);
    }

    /**
     * @return array<string, mixed>
     */
    private function successPayload(): array
    {
        return [
            'status' => 'success',
            'submission_id' => '00000000-0000-0000-0000-000000000001',
            'transcript' => '日本語を練習しています。',
            'audio_duration_seconds' => 6.5,
            'recognized_duration_seconds' => 5.0,
            'speech_rate' => ['characters_per_minute' => 180.0],
            'azure_request_id' => 'request-1',
            'azure_session_id' => 'session-1',
            'raw_azure_response' => ['recognition_mode' => 'continuous'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorPayload(
        string $errorType,
        string $detail,
        bool $retryable,
        string $userAction,
        array $diagnostic = [],
    ): array {
        return [
            'status' => 'error',
            'error_type' => $errorType,
            'detail' => $detail,
            'retryable' => $retryable,
            'user_action' => $userAction,
            'diagnostic' => $diagnostic,
        ];
    }

    private function audioPath(): string
    {
        $path = storage_path('framework/testing/recording-'.uniqid('', true).'.webm');

        file_put_contents($path, 'dummy audio bytes');

        return $path;
    }
}
