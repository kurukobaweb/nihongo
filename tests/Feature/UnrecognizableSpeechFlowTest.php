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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class UnrecognizableSpeechFlowTest extends TestCase
{
    private ?Submission $firstSubmission = null;

    private ?Submission $secondSubmission = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('T013-03 unrecognizable speech flow tests require pdo_sqlite.');
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

    public function test_422_unrecognizable_speech_fails_without_empty_result_and_resubmission_recovers(): void
    {
        Storage::fake('local');
        Bus::fake();
        Log::spy();

        $user = $this->createVerifiedUser();
        $question = $this->createQuestion($this->createCategory());

        $this->mock(PythonEvaluationClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('evaluate')
                ->twice()
                ->withArgs(function (
                    string $audioFilePath,
                    string $submissionId,
                    int $questionId,
                    int $expectedDuration,
                    array $featureFlags,
                ): bool {
                    $submission = Submission::query()->find($submissionId);

                    return is_file($audioFilePath)
                        && $submission instanceof Submission
                        && $submission->question_id === $questionId
                        && $expectedDuration === 60
                        && $featureFlags === [
                            'pronunciation_assessment' => false,
                            'fluency_assessment' => false,
                            'content_assessment' => false,
                            'comment' => ['llm_generation' => false],
                        ];
                })
                ->andReturnUsing(function (
                    string $audioFilePath,
                    string $submissionId,
                    int $questionId,
                    int $expectedDuration,
                    array $featureFlags,
                ): PythonEvaluationResult {
                    if ($this->firstSubmission instanceof Submission && $submissionId === $this->firstSubmission->id) {
                        return PythonEvaluationResult::failure(
                            errorType: 'speech_unrecognized',
                            detail: 'Speech could not be recognized',
                            httpStatus: 422,
                            retryable: false,
                            userAction: 'rerecord',
                            diagnostic: ['category' => 'no_match'],
                        );
                    }

                    if ($this->secondSubmission instanceof Submission && $submissionId === $this->secondSubmission->id) {
                        return $this->successfulEvaluationResult($submissionId);
                    }

                    $this->fail("Unexpected submission sent to Python evaluation fake: {$submissionId}");
                });
        });

        $firstResponse = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => UploadedFile::fake()->create('unrecognizable.webm', 64, 'audio/webm'),
        ]);

        $firstResponse->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('question_id', $question->id);

        $this->firstSubmission = Submission::query()->findOrFail($firstResponse->json('submission_id'));
        Storage::disk('local')->assertExists($this->firstSubmission->audio_path);
        Bus::assertDispatched(ProcessSpeechEvaluationJob::class, fn (ProcessSpeechEvaluationJob $job): bool => $job->submissionId === $this->firstSubmission->id);

        app()->call([new ProcessSpeechEvaluationJob($this->firstSubmission->id), 'handle']);

        $this->firstSubmission->refresh();

        $this->assertSame('failed', $this->firstSubmission->status);
        $this->assertSame('speech_unrecognized: Speech could not be recognized', $this->firstSubmission->error_message);
        $this->assertNotNull($this->firstSubmission->completed_at);
        $this->assertSame(0, Evaluation::query()->where('submission_id', $this->firstSubmission->id)->count());
        Storage::disk('local')->assertMissing($this->firstSubmission->audio_path);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $this->firstSubmission))
            ->assertOk()
            ->assertJsonPath('submission_id', $this->firstSubmission->id)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('completed', false)
            ->assertJsonPath('failed', true)
            ->assertJsonPath('result_url', null)
            ->assertJsonPath('error_message', 'speech_unrecognized: Speech could not be recognized')
            ->assertJsonMissingPath('evaluation')
            ->assertJsonMissingPath('error_type')
            ->assertJsonMissingPath('user_action');

        $this->actingAs($user)
            ->get(route('submissions.result', $this->firstSubmission))
            ->assertNotFound();

        $secondResponse = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => UploadedFile::fake()->create('recognized.webm', 64, 'audio/webm'),
        ]);

        $secondResponse->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('question_id', $question->id);

        $this->secondSubmission = Submission::query()->findOrFail($secondResponse->json('submission_id'));

        $this->assertNotSame($this->firstSubmission->id, $this->secondSubmission->id);
        $this->assertSame('failed', $this->firstSubmission->fresh()->status);
        Storage::disk('local')->assertExists($this->secondSubmission->audio_path);
        Bus::assertDispatched(ProcessSpeechEvaluationJob::class, fn (ProcessSpeechEvaluationJob $job): bool => $job->submissionId === $this->secondSubmission->id);
        Bus::assertDispatchedTimes(ProcessSpeechEvaluationJob::class, 2);

        app()->call([new ProcessSpeechEvaluationJob($this->secondSubmission->id), 'handle']);

        $this->firstSubmission->refresh();
        $this->secondSubmission->refresh();
        $evaluation = Evaluation::query()->where('submission_id', $this->secondSubmission->id)->sole();

        $this->assertSame('failed', $this->firstSubmission->status);
        $this->assertSame('completed', $this->secondSubmission->status);
        $this->assertNull($this->secondSubmission->error_message);
        $this->assertNotNull($this->secondSubmission->completed_at);
        $this->assertSame('日本語を練習しています。', $evaluation->transcript);
        $this->assertSame('5.00', (string) $evaluation->duration_seconds);
        $this->assertSame(180, $evaluation->characters_per_minute);
        $this->assertSame('appropriate', $evaluation->speed_assessment);
        $this->assertNull($evaluation->overall_score);
        Storage::disk('local')->assertMissing($this->secondSubmission->audio_path);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $this->secondSubmission))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('completed', true)
            ->assertJsonPath('failed', false)
            ->assertJsonPath('result_url', route('submissions.result', $this->secondSubmission))
            ->assertJsonPath('evaluation.transcript', '日本語を練習しています。');

        $this->actingAs($user)
            ->get(route('submissions.result', $this->secondSubmission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Submissions/Result')
                ->where('submission.id', $this->secondSubmission->id)
                ->where('submission.status', 'completed')
                ->where('evaluation.transcript', '日本語を練習しています。'));

        $this->assertInfoLogEvent('evaluation_job_started', $this->firstSubmission);
        $this->assertInfoLogEvent('evaluation_request_prepared', $this->firstSubmission, ['status' => 'processing']);
        $this->assertWarningLogEvent('422_non_retryable_occurred', $this->firstSubmission, [
            'retryable' => false,
            'error_category' => 'speech_unrecognized',
            'http_status_code' => 422,
        ]);
        $this->assertWarningLogEvent('submission_marked_failed', $this->firstSubmission, [
            'status' => 'failed',
            'retryable' => false,
            'error_category' => 'speech_unrecognized',
            'http_status_code' => 422,
        ]);
        $this->assertInfoLogEvent('temporary_audio_delete_succeeded', $this->firstSubmission, [
            'status' => 'failed',
            'delete_result' => 'deleted',
            'audio_file' => basename($this->firstSubmission->audio_path),
        ]);

        $this->assertInfoLogEvent('evaluation_succeeded', $this->secondSubmission, ['http_status_code' => 200]);
        $this->assertInfoLogEvent('evaluation_saved', $this->secondSubmission, ['evaluation_id' => $evaluation->id]);
        $this->assertInfoLogEvent('submission_marked_completed', $this->secondSubmission, [
            'status' => 'completed',
            'evaluation_id' => $evaluation->id,
        ]);
        $this->assertInfoLogEvent('temporary_audio_delete_succeeded', $this->secondSubmission, [
            'status' => 'completed',
            'delete_result' => 'deleted',
            'audio_file' => basename($this->secondSubmission->audio_path),
        ]);
    }

    public function test_frontend_failed_polling_stops_without_result_redirect_and_keeps_rerecord_path(): void
    {
        $pollingSource = file_get_contents(resource_path('js/Stores/useSubmissionPollingStore.js'));
        $recordingPanelSource = file_get_contents(resource_path('js/Components/Recording/RecordingPanel.vue'));

        $this->assertStringContainsString("payload.status === 'completed' || payload.status === 'failed'", $pollingSource);
        $this->assertStringContainsString('polling.stop();', $pollingSource);
        $this->assertStringContainsString("payload.status === 'completed' && payload.result_url", $pollingSource);
        $this->assertStringContainsString('window.location.assign(payload.result_url)', $pollingSource);
        $this->assertStringContainsString('RECOGNITION_FAILURE_MARKERS', $pollingSource);
        $this->assertStringContainsString('speech_unrecognized', $pollingSource);
        $this->assertStringContainsString('audio_conversion_failed', $pollingSource);
        $this->assertStringContainsString("failureKind.value === 'recognition'", $pollingSource);
        $this->assertStringContainsString('isRecognitionFailure', $pollingSource);
        $this->assertStringContainsString('submissionPollingStore.isRecognitionFailure', $recordingPanelSource);
        $this->assertStringContainsString('@click="resetRecording"', $recordingPanelSource);
        $this->assertStringNotContainsString('submissions.result', $recordingPanelSource);
        $this->assertStringNotContainsString('error_type', $pollingSource);
        $this->assertStringNotContainsString('user_action', $pollingSource);
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function assertInfoLogEvent(string $event, Submission $submission, array $expected = []): void
    {
        Log::shouldHaveReceived('info')
            ->with(\Mockery::type('string'), \Mockery::on(
                fn (array $context): bool => $this->logContextMatches($context, $event, $submission, $expected),
            ))
            ->atLeast()
            ->once();
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function assertWarningLogEvent(string $event, Submission $submission, array $expected = []): void
    {
        Log::shouldHaveReceived('warning')
            ->with(\Mockery::type('string'), \Mockery::on(
                fn (array $context): bool => $this->logContextMatches($context, $event, $submission, $expected),
            ))
            ->atLeast()
            ->once();
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function logContextMatches(
        array $context,
        string $event,
        Submission $submission,
        array $expected = [],
    ): bool {
        if (($context['event'] ?? null) !== $event
            || ($context['submission_id'] ?? null) !== $submission->id
            || ! $this->logContextIsSafe($context)) {
            return false;
        }

        foreach ($expected as $key => $value) {
            if (($context[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function logContextIsSafe(array $context): bool
    {
        $encoded = json_encode($context);

        return is_string($encoded)
            && ! array_key_exists('audio_path', $context)
            && ! array_key_exists('audioFilePath', $context)
            && ! array_key_exists('transcript', $context)
            && ! array_key_exists('rawAzureResponse', $context)
            && ! array_key_exists('raw_azure_response', $context)
            && ! str_contains($encoded, 'dummy audio')
            && ! str_contains($encoded, 'Authorization')
            && ! str_contains($encoded, 'Bearer ')
            && ! str_contains($encoded, 'token')
            && ! str_contains($encoded, 'secret')
            && ! str_contains($encoded, 'raw Azure');
    }

    private function successfulEvaluationResult(string $submissionId): PythonEvaluationResult
    {
        return new PythonEvaluationResult(
            success: true,
            httpStatus: 200,
            submissionId: $submissionId,
            transcript: '日本語を練習しています。',
            audioDurationSeconds: 6.5,
            recognizedDurationSeconds: 5.0,
            speechRate: [
                'characters_per_minute' => 180.0,
                'assessment' => 'appropriate',
            ],
            azureRequestId: 'request-1',
            azureSessionId: 'session-1',
            rawAzureResponse: ['recognition_mode' => 'continuous'],
        );
    }

    private function createVerifiedUser(): User
    {
        $user = User::query()->create([
            'name' => 'T013-03 Test User',
            'email' => uniqid('t013_03_', true).'@example.com',
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => uniqid('category_', true),
            'slug' => uniqid('category_', true),
            'description' => 'T013-03 test category',
            'display_order' => 10,
            'is_active' => true,
        ]);
    }

    private function createQuestion(Category $category): Question
    {
        return Question::query()->create([
            'category_id' => $category->id,
            'title' => uniqid('question_', true),
            'prompt_text' => 'Please speak about this topic.',
            'difficulty' => 'beginner',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'recommended_duration_seconds' => 60,
            'has_model_answer' => false,
            'model_answer_text' => null,
            'is_published' => true,
            'display_order' => 10,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('role', 20)->default('user');
            $table->string('jlpt_level', 20)->nullable();
            $table->string('google_id')->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
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
            $table->boolean('has_model_answer')->default(false);
            $table->text('model_answer_text')->nullable();
            $table->boolean('is_published')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id');
            $table->foreignId('tag_id');
            $table->primary(['question_id', 'tag_id']);
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
