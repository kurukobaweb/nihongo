<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubmissionResultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Submission result feature tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

        $this->createSchema();
    }

    public function test_guest_cannot_view_submission_result(): void
    {
        $submission = $this->createSubmission(['status' => 'completed']);
        $this->createEvaluation($submission);

        $this->get(route('submissions.result', $submission))
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_own_completed_submission_result_with_evaluation(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission([
            'user_id' => $user->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->createEvaluation($submission, [
            'transcript' => 'I am practicing Japanese.',
            'duration_seconds' => 58.30,
            'characters_per_minute' => 320,
            'speed_assessment' => 'appropriate',
            'overall_score' => 87.50,
            'comment' => 'Saved feedback comment.',
        ]);

        $this->actingAs($user)
            ->get(route('submissions.result', $submission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Submissions/Result')
                ->where('submission.id', $submission->id)
                ->where('submission.status', 'completed')
                ->where('question.title', 'Prompt')
                ->where('question.question_format.label', '単体問題')
                ->where('evaluation.transcript', 'I am practicing Japanese.')
                ->where('evaluation.duration_seconds', 58.3)
                ->where('evaluation.characters_per_minute', 320)
                ->where('evaluation.speed_assessment', 'appropriate')
                ->where('evaluation.overall_score', 87.5)
                ->where('evaluation.comment', 'Saved feedback comment.'));
    }

    public function test_other_users_completed_submission_result_returns_not_found(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $owner->id, 'status' => 'completed']);
        $this->createEvaluation($submission);

        $this->actingAs($otherUser)
            ->get(route('submissions.result', $submission))
            ->assertNotFound();
    }

    public function test_pending_processing_and_failed_submissions_do_not_show_result_page(): void
    {
        $user = $this->createUser();

        foreach (['pending', 'processing', 'failed'] as $status) {
            $submission = $this->createSubmission(['user_id' => $user->id, 'status' => $status]);

            if ($status !== 'failed') {
                $this->createEvaluation($submission);
            }

            $this->actingAs($user)
                ->get(route('submissions.result', $submission))
                ->assertNotFound();
        }
    }

    public function test_completed_submission_without_evaluation_does_not_show_result_page(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $user->id, 'status' => 'completed']);

        $this->actingAs($user)
            ->get(route('submissions.result', $submission))
            ->assertNotFound();
    }

    public function test_completed_status_api_returns_result_url_for_completed_submission_with_evaluation(): void
    {
        $user = $this->createUser();
        $submission = $this->createSubmission(['user_id' => $user->id, 'status' => 'completed']);
        $this->createEvaluation($submission);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $submission))
            ->assertOk()
            ->assertJsonPath('result_url', route('submissions.result', $submission))
            ->assertJsonPath('status', 'completed')
            ->assertJsonMissingPath('error_type')
            ->assertJsonMissingPath('user_action');
    }

    public function test_non_completed_status_api_keeps_result_url_null_and_failed_keeps_error_message_only(): void
    {
        $user = $this->createUser();

        foreach (['pending', 'processing'] as $status) {
            $submission = $this->createSubmission(['user_id' => $user->id, 'status' => $status]);

            $this->actingAs($user)
                ->getJson(route('submissions.status', $submission))
                ->assertOk()
                ->assertJsonPath('result_url', null);
        }

        $failed = $this->createSubmission([
            'user_id' => $user->id,
            'status' => 'failed',
            'error_message' => 'speech_unrecognized: Speech could not be recognized',
        ]);

        $this->actingAs($user)
            ->getJson(route('submissions.status', $failed))
            ->assertOk()
            ->assertJsonPath('result_url', null)
            ->assertJsonPath('error_message', 'speech_unrecognized: Speech could not be recognized')
            ->assertJsonMissingPath('error_type')
            ->assertJsonMissingPath('user_action');
    }

    public function test_result_route_name_and_component_are_wired(): void
    {
        $route = Route::getRoutes()->getByName('submissions.result');

        $this->assertNotNull($route);
        $this->assertSame('submissions/{submission}/result', $route->uri());

        $source = file_get_contents(resource_path('js/Pages/Submissions/Result.vue'));

        $this->assertStringContainsString('総合スコア', $source);
        $this->assertStringContainsString('速度', $source);
        $this->assertStringContainsString('transcript', $source);
        $this->assertStringContainsString('コメントはまだありません。', $source);
        $this->assertStringContainsString('次の問題を選ぶ', $source);
        $this->assertStringNotContainsString('pronunciation_result', $source);
        $this->assertStringNotContainsString('fluency_result', $source);
        $this->assertStringNotContainsString('error_type', $source);
        $this->assertStringNotContainsString('user_action', $source);
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

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('result_', true).'@example.com',
            'password' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(array $attributes = []): Submission
    {
        $category = Category::query()->create([
            'name' => 'Conversation',
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
            'user_id' => $this->createUser()->id,
            'question_id' => $question->id,
            'audio_path' => 'audio/2026/06/recording.webm',
            'audio_size_bytes' => 16,
            'status' => 'pending',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEvaluation(Submission $submission, array $attributes = []): Evaluation
    {
        return Evaluation::query()->create(array_merge([
            'submission_id' => $submission->id,
            'transcript' => 'I am practicing Japanese.',
            'duration_seconds' => 58.30,
            'characters_per_minute' => 320,
            'speed_assessment' => 'appropriate',
            'overall_score' => null,
            'comment' => null,
        ], $attributes));
    }
}
