<?php

namespace Tests\Feature;

use App\Jobs\ProcessSpeechEvaluationJob;
use App\Models\Category;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Submission upload feature tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

        $this->createSchema();
    }

    public function test_authenticated_user_can_upload_webm_audio_and_create_pending_submission(): void
    {
        Storage::fake('local');
        Bus::fake();

        $user = $this->createUser();
        $question = $this->createQuestion($this->createCategory());
        $audio = UploadedFile::fake()->create('recording.webm', 64, 'audio/webm');

        $response = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => $audio,
            'user_id' => $user->id + 100,
        ]);

        $response->assertAccepted()
            ->assertJsonStructure(['submission_id', 'status', 'question_id', 'submitted_at'])
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('question_id', $question->id)
            ->assertJsonMissingPath('id')
            ->assertJsonMissingPath('audio_path')
            ->assertJsonMissingPath('processing')
            ->assertJsonMissingPath('polling_url')
            ->assertJsonMissingPath('redirect_url');

        $submission = Submission::query()->sole();

        $this->assertSame($response->json('submission_id'), $submission->id);
        $this->assertSame($submission->id.'.webm', basename($submission->audio_path));
        $this->assertSame($user->id, $submission->user_id);
        $this->assertSame($question->id, $submission->question_id);
        $this->assertSame('pending', $submission->status);
        $this->assertNull($submission->audio_duration_seconds);
        $this->assertGreaterThan(0, $submission->audio_size_bytes);
        $this->assertMatchesRegularExpression(
            '/^audio\/\d{4}\/\d{2}\/[0-9a-f-]{36}\.webm$/',
            $submission->audio_path,
        );

        Storage::disk('local')->assertExists($submission->audio_path);
        Bus::assertDispatched(ProcessSpeechEvaluationJob::class, function (ProcessSpeechEvaluationJob $job) use ($submission) {
            return $job->submissionId === $submission->id;
        });
    }

    public function test_upload_inserts_one_database_queue_job(): void
    {
        Storage::fake('local');
        config()->set('queue.default', 'database');

        $user = $this->createUser();
        $question = $this->createQuestion($this->createCategory());
        $audio = UploadedFile::fake()->create('recording.webm', 64, 'audio/webm');

        $response = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => $audio,
        ]);

        $response->assertAccepted();

        $submissionId = $response->json('submission_id');
        $jobPayload = Schema::getConnection()
            ->table('jobs')
            ->sole();

        $this->assertSame(1, Schema::getConnection()->table('jobs')->count());
        $this->assertStringContainsString('ProcessSpeechEvaluationJob', $jobPayload->payload);
        $this->assertStringContainsString($submissionId, $jobPayload->payload);
    }

    public function test_multiple_uploads_create_separate_submissions_and_jobs(): void
    {
        Storage::fake('local');
        Bus::fake();

        $user = $this->createUser();
        $question = $this->createQuestion($this->createCategory());

        $firstResponse = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => UploadedFile::fake()->create('first.webm', 64, 'audio/webm'),
        ]);

        $secondResponse = $this->actingAs($user)->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => UploadedFile::fake()->create('second.webm', 64, 'audio/webm'),
        ]);

        $firstResponse->assertAccepted();
        $secondResponse->assertAccepted();

        $this->assertNotSame($firstResponse->json('submission_id'), $secondResponse->json('submission_id'));
        $this->assertSame(2, Submission::query()->count());
        $this->assertEqualsCanonicalizing(
            [$firstResponse->json('submission_id'), $secondResponse->json('submission_id')],
            Submission::query()->pluck('id')->all(),
        );

        Bus::assertDispatchedTimes(ProcessSpeechEvaluationJob::class, 2);
    }

    public function test_guest_cannot_upload_audio(): void
    {
        Storage::fake('local');

        $question = $this->createQuestion($this->createCategory());
        $audio = UploadedFile::fake()->create('recording.webm', 64, 'audio/webm');

        $this->postJson(route('submissions.store'), [
            'question_id' => $question->id,
            'audio' => $audio,
        ])->assertUnauthorized();

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_audio_file_is_required(): void
    {
        $question = $this->createQuestion($this->createCategory());

        $this->actingAs($this->createUser())
            ->postJson(route('submissions.store'), ['question_id' => $question->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $question = $this->createQuestion($this->createCategory());
        $audio = UploadedFile::fake()->create('recording.txt', 1, 'text/plain');

        $this->actingAs($this->createUser())
            ->postJson(route('submissions.store'), [
                'question_id' => $question->id,
                'audio' => $audio,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_mvp_provisional_audio_size_limit_is_enforced(): void
    {
        $question = $this->createQuestion($this->createCategory());
        $audio = UploadedFile::fake()->create('recording.webm', 10241, 'audio/webm');

        $this->actingAs($this->createUser())
            ->postJson(route('submissions.store'), [
                'question_id' => $question->id,
                'audio' => $audio,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
    }

    public function test_missing_question_id_is_rejected(): void
    {
        $audio = UploadedFile::fake()->create('recording.webm', 64, 'audio/webm');

        $this->actingAs($this->createUser())
            ->postJson(route('submissions.store'), [
                'question_id' => 999999,
                'audio' => $audio,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question_id');
    }

    public function test_unpublished_question_is_rejected(): void
    {
        $question = $this->createQuestion($this->createCategory(), ['is_published' => false]);
        $audio = UploadedFile::fake()->create('recording.webm', 64, 'audio/webm');

        $this->actingAs($this->createUser())
            ->postJson(route('submissions.store'), [
                'question_id' => $question->id,
                'audio' => $audio,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question_id');
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

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('submission_', true).'@example.com',
            'password' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCategory(array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => uniqid('category_', true),
            'slug' => uniqid('category_', true),
            'description' => 'Test category',
            'display_order' => 10,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createQuestion(Category $category, array $attributes = []): Question
    {
        return Question::query()->create(array_merge([
            'category_id' => $category->id,
            'title' => uniqid('question_', true),
            'prompt_text' => 'Speak about this topic.',
            'difficulty' => 'beginner',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'recommended_duration_seconds' => 60,
            'has_model_answer' => false,
            'model_answer_text' => null,
            'is_published' => true,
            'display_order' => 10,
        ], $attributes));
    }
}
