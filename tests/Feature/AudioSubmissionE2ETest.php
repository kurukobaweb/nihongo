<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AudioSubmissionE2ETest extends TestCase
{
    private ?User $user = null;

    private ?Category $category = null;

    private ?Question $question = null;

    private ?Submission $submission = null;

    protected function tearDown(): void
    {
        if ($this->submission instanceof Submission) {
            Evaluation::query()->where('submission_id', $this->submission->id)->delete();
            $this->submission->delete();
        }

        $this->question?->delete();
        $this->category?->delete();
        $this->user?->forceDelete();

        parent::tearDown();
    }

    public function test_audio_submission_reaches_completed_result_with_real_azure_stt(): void
    {
        if (getenv('RUN_AZURE_AUDIO_E2E') !== '1') {
            $this->markTestSkipped('Set RUN_AZURE_AUDIO_E2E=1 to run the local Azure audio submission E2E check.');
        }

        $samplePath = base_path('storage/app/local/test-audio/azure-stt-ja-sample.wav');

        if (! is_file($samplePath)) {
            $this->markTestSkipped('Local Azure STT sample audio is not available.');
        }

        config()->set('queue.default', 'database');
        config()->set('services.python_evaluation.base_url', env('SPEECH_SERVICE_URL', 'http://127.0.0.1:8101'));
        config()->set('services.python_evaluation.internal_token', env('SPEECH_SERVICE_INTERNAL_TOKEN'));
        config()->set('services.python_evaluation.connect_timeout', 5);
        config()->set('services.python_evaluation.read_timeout', 180);
        config()->set('features.speech_pronunciation_assessment_enabled', false);
        config()->set('features.speech_fluency_assessment_enabled', false);
        config()->set('features.speech_content_assessment_enabled', false);
        config()->set('features.comment_llm_generation_enabled', false);

        Log::spy();

        $this->user = $this->createUser();
        $this->category = $this->createCategory();
        $this->question = $this->createQuestion($this->category);

        $audio = File::createWithContent('azure-stt-ja-sample.webm', file_get_contents($samplePath))
            ->mimeType('audio/webm');

        $response = $this->actingAs($this->user)->postJson(route('submissions.store'), [
            'question_id' => $this->question->id,
            'audio' => $audio,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('question_id', $this->question->id);

        $this->submission = Submission::query()->findOrFail($response->json('submission_id'));
        Storage::disk('local')->assertExists($this->submission->audio_path);

        $this->artisan('queue:work', [
            '--once' => true,
            '--queue' => 'default',
            '--timeout' => 240,
        ])->assertExitCode(0);

        $this->submission->refresh();
        $evaluation = Evaluation::query()->where('submission_id', $this->submission->id)->sole();

        $this->assertSame('completed', $this->submission->status);
        $this->assertNull($this->submission->error_message);
        $this->assertNotNull($this->submission->completed_at);
        $this->assertSame('これは、日本語の音声認識テストです。 この音声は様々なテストに活用されます。 よろしくお願いいたします。', $evaluation->transcript);
        $this->assertNotNull($evaluation->duration_seconds);
        $this->assertNotNull($evaluation->characters_per_minute);
        $this->assertNull($evaluation->pronunciation_result);
        $this->assertNull($evaluation->fluency_result);
        Storage::disk('local')->assertMissing($this->submission->audio_path);

        $this->actingAs($this->user)
            ->getJson(route('submissions.status', $this->submission))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('completed', true)
            ->assertJsonPath('evaluation.transcript', $evaluation->transcript)
            ->assertJsonPath('result_url', route('submissions.result', $this->submission));

        $this->actingAs($this->user)
            ->get(route('submissions.result', $this->submission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Submissions/Result')
                ->where('submission.id', $this->submission->id)
                ->where('submission.status', 'completed')
                ->where('evaluation.transcript', $evaluation->transcript));

        $this->assertLogEvent('evaluation_job_started');
        $this->assertLogEvent('evaluation_request_prepared');
        $this->assertLogEvent('evaluation_succeeded');
        $this->assertLogEvent('evaluation_saved');
        $this->assertLogEvent('submission_marked_completed');
        $this->assertLogEvent('temporary_audio_delete_succeeded');
    }

    private function createUser(): User
    {
        $user = User::query()->create([
            'name' => 'T013-02 E2E User',
            'email' => 't013-02-e2e-'.uniqid().'@example.com',
            'password' => 'password',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'T013-02 E2E Category',
            'slug' => 't013-02-e2e-'.uniqid(),
            'description' => 'Temporary category for T013-02 local E2E.',
            'display_order' => 9999,
            'is_active' => true,
        ]);
    }

    private function createQuestion(Category $category): Question
    {
        return Question::query()->create([
            'category_id' => $category->id,
            'title' => 'T013-02 E2E Question',
            'prompt_text' => '日本語で短く話してください。',
            'difficulty' => 'beginner',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'recommended_duration_seconds' => 60,
            'has_model_answer' => false,
            'model_answer_text' => null,
            'is_published' => true,
            'display_order' => 9999,
        ]);
    }

    private function assertLogEvent(string $event): void
    {
        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($event): bool {
                return ($context['event'] ?? null) === $event
                    && ($context['submission_id'] ?? null) === $this->submission?->id
                    && ! array_key_exists('audio_path', $context)
                    && ! array_key_exists('transcript', $context)
                    && ! array_key_exists('raw_azure_response', $context);
            })
            ->atLeast()
            ->once();
    }
}
