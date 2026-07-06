<?php

namespace Tests\Feature;

use App\Jobs\CleanupTempFilesJob;
use App\Models\Category;
use App\Models\Question;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupTempFilesJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('CleanupTempFilesJob tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

        $this->createSchema();
    }

    public function test_completed_and_failed_residual_audio_files_are_deleted(): void
    {
        Log::spy();
        Storage::fake('local');

        $completed = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000101',
            'status' => 'completed',
            'audio_path' => 'audio/2026/06/completed.webm',
        ]);
        $failed = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000102',
            'status' => 'failed',
            'audio_path' => 'audio/2026/06/failed.webm',
        ]);
        $pending = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000103',
            'status' => 'pending',
            'audio_path' => 'audio/2026/06/pending.webm',
        ]);
        $processing = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000104',
            'status' => 'processing',
            'audio_path' => 'audio/2026/06/processing.webm',
        ]);

        foreach ([$completed, $failed, $pending, $processing] as $submission) {
            Storage::disk('local')->put($submission->audio_path, 'SECRET_AUDIO_BYTES');
        }

        app()->call([new CleanupTempFilesJob(), 'handle']);

        Storage::disk('local')->assertMissing($completed->audio_path);
        Storage::disk('local')->assertMissing($failed->audio_path);
        Storage::disk('local')->assertExists($pending->audio_path);
        Storage::disk('local')->assertExists($processing->audio_path);

        Log::shouldHaveReceived('info')
            ->with('Temporary audio file deleted.', \Mockery::on(
                fn (array $context): bool => $this->safeLogContextFor($context, $completed),
            ))
            ->once();
        Log::shouldHaveReceived('info')
            ->with('Temporary audio file deleted.', \Mockery::on(
                fn (array $context): bool => $this->safeLogContextFor($context, $failed),
            ))
            ->once();
    }

    public function test_missing_audio_file_is_handled_idempotently(): void
    {
        Log::spy();
        Storage::fake('local');

        $submission = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000201',
            'status' => 'completed',
            'audio_path' => 'audio/2026/06/missing.webm',
        ]);

        app()->call([new CleanupTempFilesJob(), 'handle']);

        Storage::disk('local')->assertMissing($submission->audio_path);
        Log::shouldHaveReceived('info')
            ->with('Temporary audio file already missing.', \Mockery::on(
                fn (array $context): bool => $this->safeLogContextFor($context, $submission),
            ))
            ->once();
    }

    public function test_delete_failure_is_logged_without_audio_contents_or_secrets(): void
    {
        Log::spy();

        $submission = $this->createSubmission([
            'id' => '00000000-0000-0000-0000-000000000301',
            'status' => 'failed',
            'audio_path' => 'audio/2026/06/delete-failure.webm',
        ]);
        $disk = \Mockery::mock();

        $disk->shouldReceive('exists')
            ->once()
            ->with($submission->audio_path)
            ->andReturn(true);
        $disk->shouldReceive('delete')
            ->once()
            ->with($submission->audio_path)
            ->andReturn(false);
        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($disk);

        app()->call([new CleanupTempFilesJob(), 'handle']);

        Log::shouldHaveReceived('warning')
            ->with('Temporary audio file was not deleted.', \Mockery::on(
                fn (array $context): bool => $this->safeLogContextFor($context, $submission),
            ))
            ->once();
    }

    private function safeLogContextFor(array $context, Submission $submission): bool
    {
        $encoded = json_encode($context);

        return $context['submission_id'] === $submission->id
            && $context['submission_status'] === $submission->status
            && $context['audio_file'] === basename($submission->audio_path)
            && $context['cleanup_context'] === 'cleanup_temp_files_job'
            && in_array($context['event'], [
                'temporary_audio_delete_succeeded',
                'temporary_audio_delete_missing',
                'temporary_audio_delete_failed',
            ], true)
            && in_array($context['delete_result'], ['deleted', 'missing', 'failed'], true)
            && is_string($encoded)
            && ! array_key_exists('audio_path', $context)
            && ! str_contains($encoded, 'SECRET_AUDIO_BYTES')
            && ! str_contains($encoded, 'secret-token')
            && ! str_contains($encoded, 'C:\\')
            && ! str_contains($encoded, '/tmp/');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSubmission(array $attributes = []): Submission
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('cleanup_', true).'@example.com',
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
    }
}
