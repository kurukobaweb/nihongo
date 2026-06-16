<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmissionStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Submission status feature tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

        $this->createSchema();
    }

    public function test_guest_cannot_view_submission_status(): void
    {
        $submission = $this->createSubmission();

        $this->getJson(route('submissions.status', $submission))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_own_pending_submission_status(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $submission))
            ->assertOk()
            ->assertJson([
                'submission_id' => $submission->id,
                'status' => 'pending',
                'completed' => false,
                'failed' => false,
                'result_url' => null,
                'error_message' => null,
            ])
            ->assertJsonMissingPath('evaluation');
    }

    public function test_authenticated_user_can_view_own_processing_submission_status(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $user->id, 'status' => 'processing']);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $submission))
            ->assertOk()
            ->assertJson([
                'submission_id' => $submission->id,
                'status' => 'processing',
                'completed' => false,
                'failed' => false,
                'result_url' => null,
                'error_message' => null,
            ])
            ->assertJsonMissingPath('evaluation');
    }

    public function test_authenticated_user_can_view_own_completed_submission_status_with_evaluation_summary(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $user->id, 'status' => 'completed']);

        Evaluation::query()->create([
            'submission_id' => $submission->id,
            'transcript' => 'I am practicing Japanese.',
            'duration_seconds' => 58.30,
            'characters_per_minute' => 320,
            'speed_assessment' => 'appropriate',
            'overall_score' => null,
        ]);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $submission))
            ->assertOk()
            ->assertJson([
                'submission_id' => $submission->id,
                'status' => 'completed',
                'completed' => true,
                'failed' => false,
                'result_url' => null,
                'error_message' => null,
                'evaluation' => [
                    'transcript' => 'I am practicing Japanese.',
                    'duration_seconds' => 58.3,
                    'characters_per_minute' => 320,
                    'speed_assessment' => 'appropriate',
                    'overall_score' => null,
                ],
            ]);
    }

    public function test_authenticated_user_can_view_own_failed_submission_status(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission([
            'user_id' => $user->id,
            'status' => 'failed',
            'error_message' => 'speech_unrecognized: Speech could not be recognized',
        ]);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $submission))
            ->assertOk()
            ->assertJson([
                'submission_id' => $submission->id,
                'status' => 'failed',
                'completed' => false,
                'failed' => true,
                'result_url' => null,
                'error_message' => 'speech_unrecognized: Speech could not be recognized',
            ])
            ->assertJsonMissingPath('evaluation')
            ->assertJsonMissingPath('error_type')
            ->assertJsonMissingPath('user_action');
    }

    public function test_other_users_submission_status_returns_not_found(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $owner->id]);

        $this->actingAs($otherUser)
            ->getJson(route('submissions.status', $submission))
            ->assertNotFound();
    }

    public function test_missing_submission_status_returns_not_found(): void
    {
        $this->actingAs($this->createUser())
            ->getJson('/api/submissions/00000000-0000-0000-0000-000000999999/status')
            ->assertNotFound();
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

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('status_', true).'@example.com',
            'password' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(array $attributes = []): Submission
    {
        $user = $attributes['user_id'] ?? $this->createUser()->id;
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
            'user_id' => $user,
            'question_id' => $question->id,
            'audio_path' => 'audio/2026/06/recording.webm',
            'audio_size_bytes' => 16,
            'status' => 'pending',
        ], $attributes));
    }
}
