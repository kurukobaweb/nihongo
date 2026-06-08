<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuestionListingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Question listing feature tests require pdo_sqlite for in-memory database checks.');
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

    public function test_guest_cannot_get_question_listing(): void
    {
        $this->getJson(route('questions.index'))->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_public_questions_with_category_and_tags(): void
    {
        $category = $this->createCategory(['name' => '日常会話', 'slug' => 'daily_conversation']);
        $tag = $this->createTag(['name' => '自己紹介', 'slug' => 'self_introduction']);
        $question = $this->createQuestion($category, [
            'title' => '自己紹介をしてください',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'has_model_answer' => true,
            'model_answer_text' => '一覧では返さない模範解答',
        ]);
        $question->tags()->attach($tag);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $question->id)
            ->assertJsonPath('data.0.category.slug', 'daily_conversation')
            ->assertJsonPath('data.0.tags.0.slug', 'self_introduction')
            ->assertJsonPath('data.0.question_format.value', 'single_prompt')
            ->assertJsonPath('data.0.question_format.label', '単体問題')
            ->assertJsonPath('data.0.has_model_answer', true)
            ->assertJsonMissingPath('data.0.model_answer_text');
    }

    public function test_unpublished_questions_are_not_returned(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, ['title' => '公開済み']);
        $this->createQuestion($category, ['title' => '非公開', 'is_published' => false]);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '公開済み');
    }

    public function test_questions_in_inactive_categories_are_not_returned(): void
    {
        $activeCategory = $this->createCategory(['slug' => 'active', 'is_active' => true]);
        $inactiveCategory = $this->createCategory(['slug' => 'inactive', 'is_active' => false]);
        $this->createQuestion($activeCategory, ['title' => 'active category question']);
        $this->createQuestion($inactiveCategory, ['title' => 'inactive category question']);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'active category question');
    }

    public function test_questions_can_be_filtered_by_difficulty(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, ['title' => 'beginner question', 'difficulty' => 'beginner']);
        $this->createQuestion($category, ['title' => 'advanced question', 'difficulty' => 'advanced']);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'difficulty' => 'advanced',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'advanced question');
    }

    public function test_questions_can_be_filtered_by_single_prompt_format(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, [
            'title' => 'single prompt question',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
        ]);
        $this->createQuestion($category, [
            'title' => 'two choice question',
            'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE,
        ]);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'single prompt question')
            ->assertJsonPath('data.0.question_format.label', '単体問題');
    }

    public function test_questions_can_be_filtered_by_two_choice_format(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, [
            'title' => 'single prompt question',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
        ]);
        $this->createQuestion($category, [
            'title' => 'two choice question',
            'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE,
        ]);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'two choice question')
            ->assertJsonPath('data.0.question_format.label', '二者択一');
    }

    public function test_invalid_question_format_returns_validation_error(): void
    {
        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'question_format' => 'mvp_verification',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('question_format');
    }

    public function test_has_model_answer_is_not_used_as_question_format_filter(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, [
            'title' => 'single prompt with answer',
            'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT,
            'has_model_answer' => true,
        ]);
        $this->createQuestion($category, [
            'title' => 'two choice without answer',
            'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE,
            'has_model_answer' => false,
        ]);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'has_model_answer' => true,
        ]));

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_question_type_is_not_used_as_a_filter(): void
    {
        $category = $this->createCategory();
        $this->createQuestion($category, ['title' => 'single prompt question']);
        $this->createQuestion($category, [
            'title' => 'two choice question',
            'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE,
        ]);

        $response = $this->actingAs($this->createUser())->getJson(route('questions.index', [
            'question_type' => 'two_choice',
        ]));

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_questions_can_be_filtered_by_category_slug_or_id(): void
    {
        $daily = $this->createCategory(['slug' => 'daily']);
        $work = $this->createCategory(['slug' => 'work']);
        $this->createQuestion($daily, ['title' => 'daily question']);
        $this->createQuestion($work, ['title' => 'work question']);

        $user = $this->createUser();

        $this->actingAs($user)
            ->getJson(route('questions.index', ['category' => 'daily']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'daily question');

        $this->actingAs($user)
            ->getJson(route('questions.index', ['category' => (string) $work->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'work question');
    }

    public function test_questions_can_be_filtered_by_tag_slug_or_id(): void
    {
        $category = $this->createCategory();
        $selfIntroduction = $this->createTag(['slug' => 'self_introduction']);
        $business = $this->createTag(['slug' => 'business']);
        $selfQuestion = $this->createQuestion($category, ['title' => 'self introduction question']);
        $businessQuestion = $this->createQuestion($category, ['title' => 'business question']);
        $selfQuestion->tags()->attach($selfIntroduction);
        $businessQuestion->tags()->attach($business);

        $user = $this->createUser();

        $this->actingAs($user)
            ->getJson(route('questions.index', ['tag' => 'self_introduction']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'self introduction question');

        $this->actingAs($user)
            ->getJson(route('questions.index', ['tag' => (string) $business->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'business question');
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('user_', true).'@example.com',
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
            'prompt_text' => '話してください。',
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
