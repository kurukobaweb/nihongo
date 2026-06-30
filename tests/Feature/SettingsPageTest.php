<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLearningSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Settings page feature tests require pdo_sqlite for in-memory database checks.');
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('session.driver', 'array');

        $this->createSchema();
    }

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_settings_routes_are_auth_only_page_and_update_endpoints(): void
    {
        $pageRoute = Route::getRoutes()->getByName('settings');
        $updateRoute = Route::getRoutes()->getByName('settings.update');

        $this->assertNotNull($pageRoute);
        $this->assertSame('settings', $pageRoute->getName());
        $this->assertSame('settings', $pageRoute->uri());
        $this->assertContains('auth', $pageRoute->gatherMiddleware());
        $this->assertContains('GET', $pageRoute->methods());

        $this->assertNotNull($updateRoute);
        $this->assertSame('settings.update', $updateRoute->getName());
        $this->assertSame('settings', $updateRoute->uri());
        $this->assertContains('auth', $updateRoute->gatherMiddleware());
        $this->assertContains('PUT', $updateRoute->methods());
    }

    public function test_authenticated_user_can_view_settings_with_default_props(): void
    {
        $this->actingAs($this->createUser())
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings')
                ->where('settings.question_format_preference', 'single_prompt')
                ->where('settings.speech_duration_seconds', 60)
                ->where('settings.timer_display_mode', 'count_down')
                ->where('settings.force_stop_enabled', true)
                ->where('settings.transcript_display_enabled', true));
    }

    public function test_authenticated_user_can_view_existing_settings_props(): void
    {
        $user = $this->createUser();
        $user->learningSetting()->create([
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 120,
            'timer_display_mode' => 'hidden',
            'force_stop_enabled' => false,
            'transcript_display_enabled' => false,
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings')
                ->where('settings.question_format_preference', 'two_choice')
                ->where('settings.speech_duration_seconds', 120)
                ->where('settings.timer_display_mode', 'hidden')
                ->where('settings.force_stop_enabled', false)
                ->where('settings.transcript_display_enabled', false));
    }

    public function test_put_settings_creates_one_user_learning_setting_record(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->from('/settings')
            ->put('/settings', $this->validPayload([
                'question_format_preference' => 'two_choice',
                'speech_duration_seconds' => 90,
                'timer_display_mode' => 'count_up',
                'force_stop_enabled' => false,
                'transcript_display_enabled' => true,
            ]))
            ->assertRedirect('/settings')
            ->assertSessionHas('status', 'settings-saved');

        $this->assertSame(1, UserLearningSetting::query()->count());
        $this->assertDatabaseHas('user_learning_settings', [
            'user_id' => $user->id,
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 90,
            'timer_display_mode' => 'count_up',
            'force_stop_enabled' => false,
            'transcript_display_enabled' => true,
        ]);
    }

    public function test_repeated_save_updates_same_user_learning_setting_record(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->put('/settings', $this->validPayload([
            'speech_duration_seconds' => 60,
        ]));

        $settingId = $user->learningSetting()->sole()->id;

        $this->actingAs($user)->put('/settings', $this->validPayload([
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 180,
            'timer_display_mode' => 'hidden',
            'force_stop_enabled' => false,
            'transcript_display_enabled' => false,
        ]));

        $this->assertSame(1, UserLearningSetting::query()->where('user_id', $user->id)->count());
        $this->assertSame($settingId, $user->learningSetting()->sole()->id);
        $this->assertDatabaseHas('user_learning_settings', [
            'id' => $settingId,
            'user_id' => $user->id,
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 180,
            'timer_display_mode' => 'hidden',
            'force_stop_enabled' => false,
            'transcript_display_enabled' => false,
        ]);
    }

    public function test_settings_are_separated_by_user(): void
    {
        $firstUser = $this->createUser();
        $secondUser = $this->createUser();

        $this->actingAs($firstUser)->put('/settings', $this->validPayload([
            'question_format_preference' => 'single_prompt',
            'speech_duration_seconds' => 30,
            'timer_display_mode' => 'count_down',
        ]));

        $this->actingAs($secondUser)->put('/settings', $this->validPayload([
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 120,
            'timer_display_mode' => 'hidden',
        ]));

        $this->assertSame(2, UserLearningSetting::query()->count());
        $this->assertDatabaseHas('user_learning_settings', [
            'user_id' => $firstUser->id,
            'question_format_preference' => 'single_prompt',
            'speech_duration_seconds' => 30,
            'timer_display_mode' => 'count_down',
        ]);
        $this->assertDatabaseHas('user_learning_settings', [
            'user_id' => $secondUser->id,
            'question_format_preference' => 'two_choice',
            'speech_duration_seconds' => 120,
            'timer_display_mode' => 'hidden',
        ]);
    }

    public function test_invalid_settings_return_validation_errors(): void
    {
        $this->actingAs($this->createUser())
            ->from('/settings')
            ->put('/settings', [
                'question_format_preference' => 'jsonb_setting',
                'speech_duration_seconds' => 10,
                'timer_display_mode' => 'unknown',
                'force_stop_enabled' => 'not-boolean',
                'transcript_display_enabled' => 'not-boolean',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHasErrors([
                'question_format_preference',
                'speech_duration_seconds',
                'timer_display_mode',
                'force_stop_enabled',
                'transcript_display_enabled',
            ]);

        $this->assertSame(0, UserLearningSetting::query()->count());
    }

    public function test_users_table_does_not_store_settings_json(): void
    {
        $userColumns = Schema::getColumnListing('users');
        $source = file_get_contents(resource_path('js/Pages/Settings.vue'));

        $this->assertNotContains('settings', $userColumns);
        $this->assertNotContains('learning_settings', $userColumns);
        $this->assertNotContains('settings_json', $userColumns);
        $this->assertStringNotContainsString('localStorage', $source);
        $this->assertStringNotContainsString('sessionStorage', $source);
        $this->assertStringNotContainsString('document.cookie', $source);
        $this->assertStringNotContainsString('fetch(', $source);
        $this->assertStringNotContainsString('axios', $source);
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

        Schema::create('user_learning_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->string('question_format_preference', 50)->default('single_prompt');
            $table->integer('speech_duration_seconds')->default(60);
            $table->string('timer_display_mode', 50)->default('count_down');
            $table->boolean('force_stop_enabled')->default(true);
            $table->boolean('transcript_display_enabled')->default(true);
            $table->timestamps();
        });
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => uniqid('settings_', true).'@example.com',
            'password' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'question_format_preference' => 'single_prompt',
            'speech_duration_seconds' => 60,
            'timer_display_mode' => 'count_down',
            'force_stop_enabled' => true,
            'transcript_display_enabled' => true,
        ], $overrides);
    }
}
