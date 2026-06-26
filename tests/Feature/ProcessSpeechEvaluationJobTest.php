<?php

namespace Tests\Feature;

use App\Dto\PythonEvaluationResult;
use App\Jobs\ProcessSpeechEvaluationJob;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use App\Services\PythonEvaluationClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ProcessSpeechEvaluationJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('ProcessSpeechEvaluationJob tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');
        config()->set('features.speech_pronunciation_assessment_enabled', false);
        config()->set('features.speech_fluency_assessment_enabled', false);
        config()->set('features.speech_content_assessment_enabled', false);
        config()->set('features.comment_llm_generation_enabled', false);

        $this->createSchema();
    }

    public function test_pending_submission_is_completed_and_evaluation_is_saved(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission();
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($submission) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->withArgs(function (
                    string $audioFilePath,
                    string $submissionId,
                    int $questionId,
                    int $expectedDuration,
                    array $featureFlags,
                ) use ($submission) {
                    return is_file($audioFilePath)
                        && $submissionId === $submission->id
                        && $questionId === $submission->question_id
                        && $expectedDuration === 60
                        && $featureFlags === [
                            'pronunciation_assessment' => false,
                            'fluency_assessment' => false,
                            'content_assessment' => false,
                            'comment' => ['llm_generation' => false],
                        ];
                })
                ->andReturn($this->successfulEvaluationResult($submission->id));
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $submission->refresh();
        $evaluation = Evaluation::query()->sole();

        $this->assertSame('completed', $submission->status);
        $this->assertNull($submission->error_message);
        $this->assertNotNull($submission->completed_at);
        $this->assertSame($submission->id, $evaluation->submission_id);
        $this->assertSame('日本語を練習しています。', $evaluation->transcript);
        $this->assertSame('5.00', (string) $evaluation->duration_seconds);
        $this->assertSame(180, $evaluation->characters_per_minute);
        $this->assertSame('appropriate', $evaluation->speed_assessment);
        $this->assertSame('request-1', $evaluation->azure_request_id);
        $this->assertSame(['recognition_mode' => 'continuous'], $evaluation->raw_azure_response);
        $this->assertNull($evaluation->overall_score);
        $this->assertSame(config('comment_templates.templates.appropriate.short.0'), $evaluation->comment);
        $this->assertNull($evaluation->pronunciation_result);
        $this->assertNull($evaluation->fluency_result);
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    public function test_fallback_comment_is_saved_when_speech_rate_is_missing(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission();
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($submission) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($this->successfulEvaluationResult($submission->id, speechRate: []));
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $submission->refresh();
        $evaluation = Evaluation::query()->sole();

        $this->assertSame('completed', $submission->status);
        $this->assertNull($evaluation->characters_per_minute);
        $this->assertNull($evaluation->speed_assessment);
        $this->assertSame(config('comment_templates.fallback'), $evaluation->comment);
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    public function test_completed_submission_is_not_processed_twice(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission();
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($submission) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($this->successfulEvaluationResult($submission->id));
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);
        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $this->assertSame(1, Evaluation::query()->count());
        $this->assertSame('completed', $submission->refresh()->status);
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    public function test_failed_submission_is_not_processed(): void
    {
        $submission = $this->createSubmission(['status' => 'failed']);

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('evaluate');
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $this->assertSame('failed', $submission->refresh()->status);
        $this->assertSame(0, Evaluation::query()->count());
    }

    public function test_processing_submission_can_be_retried_and_completed(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission(['status' => 'processing']);
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($submission) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($this->successfulEvaluationResult($submission->id));
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $this->assertSame('completed', $submission->refresh()->status);
        $this->assertSame(1, Evaluation::query()->count());
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    public function test_speech_unrecognized_422_marks_submission_failed_without_evaluation(): void
    {
        $this->assertFailureResultMarksSubmissionFailed(
            PythonEvaluationResult::failure(
                errorType: 'speech_unrecognized',
                detail: 'Speech could not be recognized',
                httpStatus: 422,
                retryable: false,
                userAction: 'rerecord',
            ),
            'speech_unrecognized: Speech could not be recognized',
        );
    }

    public function test_audio_conversion_failed_422_marks_submission_failed_without_evaluation(): void
    {
        $this->assertFailureResultMarksSubmissionFailed(
            PythonEvaluationResult::failure(
                errorType: 'audio_conversion_failed',
                detail: 'Audio conversion failed',
                httpStatus: 422,
                retryable: false,
                userAction: 'rerecord',
            ),
            'audio_conversion_failed: Audio conversion failed',
        );
    }

    public function test_500_failure_marks_submission_failed_without_retry_control(): void
    {
        $this->assertFailureResultMarksSubmissionFailed(
            PythonEvaluationResult::failure(
                errorType: 'azure_configuration_unavailable',
                detail: 'Azure Speech configuration is not available',
                httpStatus: 500,
                retryable: false,
                userAction: 'contact_admin',
            ),
            'azure_configuration_unavailable: Azure Speech configuration is not available',
        );
    }

    public function test_503_retryable_failure_throws_for_retry_without_creating_evaluation(): void
    {
        $this->assertRetryableResultThrowsForRetry(
            PythonEvaluationResult::failure(
                errorType: 'azure_service_unavailable',
                detail: 'Azure Speech SDK error',
                httpStatus: 503,
                retryable: true,
                userAction: 'retry_later',
            ),
            'azure_service_unavailable: Azure Speech SDK error',
        );
    }

    public function test_timeout_retryable_failure_throws_for_retry_without_creating_evaluation(): void
    {
        $this->assertRetryableResultThrowsForRetry(
            PythonEvaluationResult::failure(
                errorType: 'python_read_timeout',
                detail: 'Python evaluation request timed out',
                retryable: true,
            ),
            'python_read_timeout: Python evaluation request timed out',
        );
    }

    public function test_connection_retryable_failure_throws_for_retry_without_creating_evaluation(): void
    {
        $this->assertRetryableResultThrowsForRetry(
            PythonEvaluationResult::failure(
                errorType: 'python_connection_failed',
                detail: 'Python evaluation service is unavailable',
                retryable: true,
            ),
            'python_connection_failed: Python evaluation service is unavailable',
        );
    }

    public function test_failed_callback_marks_submission_failed_after_retries_are_exhausted(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission(['status' => 'processing']);
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        (new ProcessSpeechEvaluationJob($submission->id))->failed(
            new RuntimeException('azure_service_unavailable: Azure Speech SDK error'),
        );

        $submission->refresh();

        $this->assertSame('failed', $submission->status);
        $this->assertSame('azure_service_unavailable: Azure Speech SDK error', $submission->error_message);
        $this->assertNotNull($submission->completed_at);
        $this->assertSame(0, Evaluation::query()->count());
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    public function test_delete_failure_does_not_break_final_failure_update(): void
    {
        $submission = $this->createSubmission(['status' => 'processing']);
        $disk = \Mockery::mock();

        $disk->shouldReceive('delete')
            ->once()
            ->with($submission->audio_path)
            ->andReturn(false);
        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($disk);

        (new ProcessSpeechEvaluationJob($submission->id))->failed(
            new RuntimeException('python_read_timeout: Python evaluation request timed out'),
        );

        $submission->refresh();

        $this->assertSame('failed', $submission->status);
        $this->assertSame('python_read_timeout: Python evaluation request timed out', $submission->error_message);
        $this->assertNotNull($submission->completed_at);
        $this->assertSame(0, Evaluation::query()->count());
    }

    public function test_retry_policy_is_bounded_with_backoff_seconds(): void
    {
        $job = new ProcessSpeechEvaluationJob('00000000-0000-0000-0000-000000000001');

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff());
    }

    public function test_missing_temporary_audio_file_does_not_break_completion(): void
    {
        Storage::fake('local');

        $submission = $this->createSubmission();

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($submission) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($this->successfulEvaluationResult($submission->id));
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $this->assertSame('completed', $submission->refresh()->status);
        $this->assertSame(1, Evaluation::query()->count());
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    private function assertFailureResultMarksSubmissionFailed(
        PythonEvaluationResult $result,
        string $expectedErrorMessage,
    ): void {
        Storage::fake('local');

        $submission = $this->createSubmission();
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($result) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($result);
        });

        app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);

        $submission->refresh();

        $this->assertSame('failed', $submission->status);
        $this->assertSame($expectedErrorMessage, $submission->error_message);
        $this->assertNotNull($submission->completed_at);
        $this->assertSame(0, Evaluation::query()->count());
        Storage::disk('local')->assertMissing($submission->audio_path);
    }

    private function assertRetryableResultThrowsForRetry(
        PythonEvaluationResult $result,
        string $expectedErrorMessage,
    ): void {
        Storage::fake('local');

        $submission = $this->createSubmission();
        Storage::disk('local')->put($submission->audio_path, 'dummy audio');

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) use ($result) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->andReturn($result);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        try {
            app()->call([new ProcessSpeechEvaluationJob($submission->id), 'handle']);
        } finally {
            $submission->refresh();

            $this->assertSame('processing', $submission->status);
            $this->assertNull($submission->error_message);
            $this->assertNull($submission->completed_at);
            $this->assertSame(0, Evaluation::query()->count());
            Storage::disk('local')->assertExists($submission->audio_path);
        }
    }

    private function successfulEvaluationResult(string $submissionId, array|null $speechRate = null): PythonEvaluationResult
    {
        return new PythonEvaluationResult(
            success: true,
            httpStatus: 200,
            submissionId: $submissionId,
            transcript: '日本語を練習しています。',
            audioDurationSeconds: 6.5,
            recognizedDurationSeconds: 5.0,
            speechRate: $speechRate ?? [
                'characters_per_minute' => 180.0,
                'assessment' => 'appropriate',
            ],
            azureRequestId: 'request-1',
            azureSessionId: 'session-1',
            rawAzureResponse: ['recognition_mode' => 'continuous'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(array $attributes = []): Submission
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('job_', true).'@example.com',
            'password' => 'password',
        ]);
        $category = Category::query()->create([
            'name' => uniqid('category_', true),
            'slug' => uniqid('category_', true),
            'is_active' => true,
        ]);
        $question = Question::query()->create([
            'category_id' => $category->id,
            'title' => 'Prompt',
            'prompt_text' => 'Speak about this topic.',
            'difficulty' => 'beginner',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'recommended_duration_seconds' => 60,
            'is_published' => true,
        ]);

        return Submission::query()->create(array_merge([
            'id' => '00000000-0000-0000-0000-'.str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'question_id' => $question->id,
            'audio_path' => 'audio/2026/06/recording.webm',
            'audio_size_bytes' => 16,
            'status' => 'pending',
        ], $attributes));
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('title');
            $table->text('prompt_text');
            $table->string('difficulty', 20);
            $table->string('question_format', 50);
            $table->integer('recommended_duration_seconds')->default(60);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id');
            $table->foreignId('question_id');
            $table->string('audio_path', 500);
            $table->integer('audio_size_bytes')->nullable();
            $table->decimal('audio_duration_seconds', 8, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->text('transcript')->nullable();
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->integer('characters_per_minute')->nullable();
            $table->string('speed_assessment', 20)->nullable();
            $table->json('pronunciation_result')->nullable();
            $table->json('fluency_result')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->string('azure_request_id')->nullable();
            $table->json('raw_azure_response')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->timestamps();
        });
    }
}
