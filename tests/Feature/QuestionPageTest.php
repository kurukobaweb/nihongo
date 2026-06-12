<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuestionPageTest extends TestCase
{
    public function test_guest_is_redirected_from_question_page(): void
    {
        $this->get('/questions')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_question_page(): void
    {
        $this->createUsersTableForAuthRouteTest();

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'question-page@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($user)
            ->get('/questions')
            ->assertOk();
    }

    public function test_question_page_vue_contains_filters_and_selection_flow(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Questions/Index.vue'));

        $this->assertStringContainsString('difficultyOptions', $source);
        $this->assertStringContainsString('formatOptions', $source);
        $this->assertStringContainsString('single_prompt', $source);
        $this->assertStringContainsString('two_choice', $source);
        $this->assertStringContainsString('単体問題', $source);
        $this->assertStringContainsString('二者択一', $source);
        $this->assertStringContainsString('/api/questions', $source);
        $this->assertStringContainsString('/dashboard?question_id=', $source);
        $this->assertStringContainsString('この問題を選択', $source);
        $this->assertStringContainsString('模範解答あり', $source);
        $this->assertStringContainsString('模範解答なし', $source);
        $this->assertStringNotContainsString('model_answer_text', $source);
        $this->assertStringNotContainsString('question_type', $source);
    }

    public function test_has_model_answer_is_not_used_as_a_question_format_filter_in_vue(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Questions/Index.vue'));

        $this->assertStringContainsString('question.has_model_answer', $source);
        $this->assertStringNotContainsString("params.set('has_model_answer'", $source);
        $this->assertStringNotContainsString('v-model="hasModelAnswer"', $source);
    }

    public function test_mvp_question_seeder_is_idempotent_and_uses_only_question_format(): void
    {
        $source = file_get_contents(database_path('seeders/MvpQuestionSeeder.php'));

        $this->assertSame(50, substr_count($source, "'category_slug' =>"));
        $this->assertStringContainsString('updateOrCreate', $source);
        $this->assertStringContainsString('$model->tags()->sync($tagIds)', $source);
        $this->assertStringContainsString('QUESTION_FORMAT_SINGLE_PROMPT', $source);
        $this->assertStringContainsString('QUESTION_FORMAT_TWO_CHOICE', $source);
        $this->assertStringContainsString("'has_model_answer' => false", $source);
        $this->assertStringNotContainsString('question_type', $source);
    }

    private function createUsersTableForAuthRouteTest(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Question page route tests require pdo_sqlite for in-memory auth checks.');
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
    }
}
