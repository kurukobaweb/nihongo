<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSelectedQuestionTest extends TestCase
{
    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_route_keeps_name_and_verified_middleware(): void
    {
        $route = Route::getRoutes()->getByName('dashboard');

        $this->assertNotNull($route);
        $this->assertSame('dashboard', $route->getName());
        $this->assertSame('dashboard', $route->uri());
        $this->assertContains('verified', $route->gatherMiddleware());
    }

    public function test_dashboard_without_question_id_has_no_selected_question(): void
    {
        $this->createSchemaForDashboardQuestionTest();

        $this->actingAs($this->createVerifiedUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('selectedQuestion', null)
                ->where('selectedQuestionUnavailable', false));
    }

    public function test_public_question_in_active_category_is_passed_to_dashboard(): void
    {
        $this->createSchemaForDashboardQuestionTest();

        $category = $this->createCategory(['name' => 'Self introduction', 'slug' => 'self_introduction']);
        $tag = $this->createTag(['name' => 'Daily conversation', 'slug' => 'daily_conversation']);
        $question = $this->createQuestion($category, [
            'title' => 'Please introduce yourself',
            'prompt_text' => 'Tell us about yourself.',
            'model_answer_text' => 'This should not be passed to Dashboard.',
            'has_model_answer' => true,
        ]);
        $question->tags()->attach($tag);

        $this->actingAs($this->createVerifiedUser())
            ->get(route('dashboard', ['question_id' => $question->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('selectedQuestion.id', $question->id)
                ->where('selectedQuestion.title', 'Please introduce yourself')
                ->where('selectedQuestion.prompt_text', 'Tell us about yourself.')
                ->where('selectedQuestion.category.name', 'Self introduction')
                ->where('selectedQuestion.difficulty', 'beginner')
                ->where('selectedQuestion.question_format.value', 'single_prompt')
                ->where('selectedQuestion.question_format.label', '単体問題')
                ->where('selectedQuestion.recommended_duration_seconds', 60)
                ->where('selectedQuestion.has_model_answer', true)
                ->where('selectedQuestion.tags.0.name', 'Daily conversation')
                ->where('selectedQuestionUnavailable', false)
                ->missing('selectedQuestion.model_answer_text'));
    }

    public function test_unpublished_question_is_not_passed_to_dashboard(): void
    {
        $this->createSchemaForDashboardQuestionTest();

        $category = $this->createCategory();
        $question = $this->createQuestion($category, ['is_published' => false]);

        $this->actingAs($this->createVerifiedUser())
            ->get(route('dashboard', ['question_id' => $question->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedQuestion', null)
                ->where('selectedQuestionUnavailable', true));
    }

    public function test_question_in_inactive_category_is_not_passed_to_dashboard(): void
    {
        $this->createSchemaForDashboardQuestionTest();

        $category = $this->createCategory(['is_active' => false]);
        $question = $this->createQuestion($category);

        $this->actingAs($this->createVerifiedUser())
            ->get(route('dashboard', ['question_id' => $question->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedQuestion', null)
                ->where('selectedQuestionUnavailable', true));
    }

    public function test_invalid_question_id_keeps_dashboard_visible_with_unavailable_state(): void
    {
        $this->createSchemaForDashboardQuestionTest();

        $this->actingAs($this->createVerifiedUser())
            ->get(route('dashboard', ['question_id' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedQuestion', null)
                ->where('selectedQuestionUnavailable', true));
    }

    public function test_dashboard_vue_contains_recording_submission_polling_flow(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Dashboard.vue'));
        $recordingPanelSource = file_get_contents(resource_path('js/Components/Recording/RecordingPanel.vue'));
        $audioRecorderSource = file_get_contents(resource_path('js/Composables/useAudioRecorder.js'));
        $pollingSource = file_get_contents(resource_path('js/Composables/usePolling.js'));
        $recordingStoreSource = file_get_contents(resource_path('js/Stores/useRecordingStore.js'));
        $submissionPollingStoreSource = file_get_contents(resource_path('js/Stores/useSubmissionPollingStore.js'));

        $this->assertStringContainsString('selectedQuestion', $source);
        $this->assertStringContainsString('selectedQuestionUnavailable', $source);
        $this->assertStringContainsString('RecordingPanel', $source);
        $this->assertStringContainsString(':question-id="selectedQuestion.id"', $source);
        $this->assertStringContainsString('question_format.label', $source);
        $this->assertStringContainsString('recommended_duration_seconds', $source);
        $this->assertStringContainsString('has_model_answer', $source);
        $this->assertStringContainsString('START', $recordingPanelSource);
        $this->assertStringContainsString('STOP', $recordingPanelSource);
        $this->assertStringContainsString('提出する', $recordingPanelSource);
        $this->assertStringContainsString('3秒間隔で最大60回', $recordingPanelSource);
        $this->assertStringContainsString('解析完了', $recordingPanelSource);
        $this->assertStringContainsString('確認タイムアウト', $recordingPanelSource);
        $this->assertStringContainsString('useRecordingStore', $recordingPanelSource);
        $this->assertStringContainsString('useSubmissionPollingStore', $recordingPanelSource);
        $this->assertStringContainsString('submitRecording', $recordingPanelSource);
        $this->assertStringContainsString('useAudioRecorder', $recordingStoreSource);
        $this->assertStringContainsString('MediaRecorder', $audioRecorderSource);
        $this->assertStringContainsString('audio/webm;codecs=opus', $audioRecorderSource);
        $this->assertStringContainsString('Blob', $audioRecorderSource);
        $this->assertStringContainsString('intervalMs = 3000', $pollingSource);
        $this->assertStringContainsString('maxAttempts = 60', $pollingSource);
        $this->assertStringContainsString('onUnmounted', $pollingSource);
        $this->assertStringContainsString('window.setInterval', $pollingSource);
        $this->assertStringContainsString("axios.post('/api/submissions'", $submissionPollingStoreSource);
        $this->assertStringContainsString('MAX_POLLING_ATTEMPTS = 60', $submissionPollingStoreSource);
        $this->assertStringContainsString('POLLING_INTERVAL_MS = 3000', $submissionPollingStoreSource);
        $this->assertStringContainsString('window.location.assign', $submissionPollingStoreSource);
        $this->assertStringContainsString('RECOGNITION_FAILURE_MARKERS', $submissionPollingStoreSource);
        $this->assertStringContainsString('speech_unrecognized', $submissionPollingStoreSource);
        $this->assertStringContainsString('audio_conversion_failed', $submissionPollingStoreSource);
        $this->assertStringContainsString("failureKind.value === 'recognition'", $submissionPollingStoreSource);
        $this->assertStringContainsString('isRecognitionFailure', $submissionPollingStoreSource);
        $this->assertStringContainsString('URL.createObjectURL', $recordingStoreSource);
        $this->assertStringContainsString('URL.revokeObjectURL', $recordingStoreSource);
        $this->assertStringContainsString('submission_id', $recordingPanelSource);
        $this->assertStringContainsString('polling', $recordingPanelSource);
        $this->assertStringContainsString('failedPanelTitle', $recordingPanelSource);
        $this->assertStringContainsString('failedPanelHelp', $recordingPanelSource);
        $this->assertStringContainsString('submissionPollingStore.isRecognitionFailure', $recordingPanelSource);
        $this->assertStringContainsString('再録音して再提出する', $recordingPanelSource);
        $this->assertStringContainsString('@click="resetRecording"', $recordingPanelSource);
        $this->assertStringNotContainsString('submissions.result', $recordingPanelSource);
        $this->assertStringNotContainsString('未評価', $recordingPanelSource);
        $this->assertStringNotContainsString('model_answer_text', $source);
        $this->assertStringNotContainsString('question_type', $source);
        $this->assertStringNotContainsString('model_answer_text', $recordingPanelSource);
        $this->assertStringNotContainsString('question_type', $recordingPanelSource);
        $this->assertStringNotContainsString('fetch(', $recordingPanelSource);
        $this->assertStringNotContainsString('fetch(', $audioRecorderSource);
        $this->assertStringNotContainsString('fetch(', $recordingStoreSource);
        $this->assertStringNotContainsString('axios', $recordingPanelSource);
        $this->assertStringNotContainsString('axios', $audioRecorderSource);
        $this->assertStringNotContainsString('axios', $recordingStoreSource);
        $this->assertStringNotContainsString('submission_id', $recordingStoreSource);
        $this->assertStringNotContainsString('polling', $recordingStoreSource);
        $this->assertStringNotContainsString('WebSocket', $pollingSource);
        $this->assertStringNotContainsString('error_type', $submissionPollingStoreSource);
        $this->assertStringNotContainsString('user_action', $submissionPollingStoreSource);
        $this->assertStringNotContainsString('error_type', $recordingPanelSource);
        $this->assertStringNotContainsString('user_action', $recordingPanelSource);
    }

    public function test_dashboard_controller_does_not_select_model_answer_text_or_question_type(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/DashboardController.php'));

        $this->assertStringContainsString("'is_published', true", $source);
        $this->assertStringContainsString("'is_active', true", $source);
        $this->assertStringNotContainsString('model_answer_text', $source);
        $this->assertStringNotContainsString('question_type', $source);
    }

    private function createSchemaForDashboardQuestionTest(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Dashboard selected question tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

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

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
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

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id');
            $table->foreignId('tag_id');
            $table->primary(['question_id', 'tag_id']);
        });
    }

    private function createVerifiedUser(): User
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('dashboard_', true).'@example.com',
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
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
    private function createTag(array $attributes = []): Tag
    {
        return Tag::query()->create(array_merge([
            'name' => uniqid('tag_', true),
            'slug' => uniqid('tag_', true),
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
            'prompt_text' => 'Please speak about this topic.',
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
