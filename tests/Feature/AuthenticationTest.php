<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Consent;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Authentication feature tests require pdo_sqlite for in-memory database checks.');
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

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('document_type', 50);
            $table->string('document_version', 50);
            $table->timestamp('agreed_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document_type', 'document_version']);
        });

        config()->set('legal.terms_of_service_version', 'test-terms-v1');
        config()->set('legal.privacy_policy_version', 'test-privacy-v1');
    }

    public function test_registration_requires_valid_input(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name', 'email', 'password', 'terms_of_service', 'privacy_policy']);
        $this->assertGuest();
    }

    public function test_registration_requires_terms_of_service_consent(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'privacy_policy' => true,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('terms_of_service');
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Consent::query()->count());
    }

    public function test_registration_requires_privacy_policy_consent(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_of_service' => true,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('privacy_policy');
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Consent::query()->count());
    }

    public function test_user_can_register_with_email_and_password(): void
    {
        Notification::fake();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_USER_AGENT' => 'Nihongo Test Browser',
            ])
            ->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'terms_of_service' => true,
                'privacy_policy' => true,
            ]);

        $response->assertRedirect('/verify-email');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password', $user->password));
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'document_type' => 'terms_of_service',
            'document_version' => 'test-terms-v1',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Nihongo Test Browser',
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'document_type' => 'privacy_policy',
            'document_version' => 'test-privacy-v1',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Nihongo Test Browser',
        ]);
        $this->assertSame(2, $user->consents()->count());
        $this->assertSame(
            2,
            $user->consents()
                ->select('document_type', 'document_version')
                ->distinct()
                ->count()
        );
        $this->assertTrue($user->consents()->whereNotNull('agreed_at')->exists());
    }

    public function test_registration_allows_reusing_soft_deleted_user_email(): void
    {
        $deletedUser = User::query()->create([
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
            'password' => Hash::make('password'),
        ]);
        $deletedUser->delete();

        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'deleted@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms_of_service' => true,
            'privacy_policy' => true,
        ]);

        $response->assertRedirect('/verify-email');
        $this->assertAuthenticated();
        $this->assertSame(2, User::withTrashed()->where('email', 'deleted@example.com')->count());
    }

    public function test_login_requires_valid_input(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_unregistered_email_cannot_login(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $deletedUser = User::query()->create([
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
            'password' => Hash::make('password'),
        ]);
        $deletedUser->delete();

        $response = $this->from('/login')->post('/login', [
            'email' => 'deleted@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_protected_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/verify-email');
    }

    public function test_verified_user_can_view_dashboard(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_verification_notice_is_available_to_unverified_user(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertOk();
    }

    public function test_verified_user_is_redirected_from_verification_notice(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertRedirect('/dashboard');
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_is_redirected_when_resending_verification_email(): void
    {
        Notification::fake();

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertRedirect('/dashboard');
        Notification::assertNothingSent();
    }

    public function test_user_can_verify_email_with_signed_link(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect('/dashboard?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_cannot_verify_email_with_invalid_hash(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('other@example.com')]
        );

        $response = $this->actingAs($user)->get($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect('/forgot-password');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/login');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->from('/reset-password/invalid-token')->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/reset-password/invalid-token');
        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_authenticated_user_is_redirected_from_auth_pages(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/register')->assertRedirect('/dashboard');
    }
}
