<?php

namespace Tests\Feature\Verification;

use App\Http\Controllers\Verification\T00006MediaRecorderController;
use App\Models\User;
use App\Services\Verification\T00006AudioAnalyzer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class T00006MediaRecorderTest extends TestCase
{
    private string $measurementPath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('T000-06 feature tests require pdo_sqlite for auth middleware checks.');
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

        $this->measurementPath = storage_path('framework/testing/t000-06-'.bin2hex(random_bytes(6)));
        config()->set('t000-06.base_path', $this->measurementPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->measurementPath);

        parent::tearDown();
    }

    public function test_guest_cannot_open_measurement_page(): void
    {
        $this->get('/verification/t000-06/media-recorder')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_open_measurement_page(): void
    {
        $this->actingAs($this->createUser(verified: false))
            ->get('/verification/t000-06/media-recorder')
            ->assertRedirect('/verify-email');
    }

    public function test_verified_user_can_open_testing_route_and_receives_inertia_page(): void
    {
        $route = Route::getRoutes()->getByName('verification.t000-06.media-recorder.show');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());

        $this->actingAs($this->createUser())
            ->get('/verification/t000-06/media-recorder')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Verification/T00006MediaRecorder')
                ->where('profiles', [10, 40, 60, 90, 120])
                ->where('initialTrial', [
                    'environment_id' => 'env-a',
                    'profile_seconds' => 10,
                    'run_number' => 1,
                    'attempt_number' => 1,
                ])
                ->where('initialTrialId', 'env-a-p010-r01-a01'));
    }

    public function test_valid_query_restores_the_run_2_trial_identity(): void
    {
        $this->actingAs($this->createUser())
            ->get('/verification/t000-06/media-recorder?environment_id=env-a&profile_seconds=10&run_number=2&attempt_number=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('initialTrial', [
                    'environment_id' => 'env-a',
                    'profile_seconds' => 10,
                    'run_number' => 2,
                    'attempt_number' => 1,
                ])
                ->where('initialTrialId', 'env-a-p010-r02-a01'));
    }

    public function test_partial_or_invalid_query_is_rejected_with_http_422(): void
    {
        $user = $this->createUser();
        $queries = [
            'environment_id=env-a',
            'environment_id=ENV_A&profile_seconds=10&run_number=2&attempt_number=1',
            'environment_id=env-a&profile_seconds=30&run_number=2&attempt_number=1',
            'environment_id=env-a&profile_seconds=10&run_number=0&attempt_number=1',
            'environment_id=env-a&profile_seconds=10&run_number=6&attempt_number=1',
            'environment_id=env-a&profile_seconds=10&run_number=2&attempt_number=0',
            'environment_id=env-a&profile_seconds=10&run_number=2&attempt_number=100',
        ];

        foreach ($queries as $query) {
            $this->actingAs($user)
                ->getJson('/verification/t000-06/media-recorder?'.$query)
                ->assertUnprocessable()
                ->assertJsonPath('message', 'The trial identity is invalid.');
        }
    }

    public function test_preflight_route_requires_verified_auth_and_keeps_local_testing_guard(): void
    {
        $route = Route::getRoutes()->getByName('verification.t000-06.media-recorder.preflight');

        $this->assertNotNull($route);
        $this->assertSame('verification/t000-06/media-recorder/trials/preflight', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
        $this->assertStringContainsString(
            "if (app()->environment(['local', 'testing']))",
            file_get_contents(base_path('routes/web.php')),
        );

        $this->post(route('verification.t000-06.media-recorder.preflight'), $this->preflightIdentity())
            ->assertRedirect('/login');

        $this->actingAs($this->createUser(verified: false))
            ->post(route('verification.t000-06.media-recorder.preflight'), $this->preflightIdentity())
            ->assertRedirect('/verify-email');
    }

    public function test_preflight_reports_available_without_creating_artifacts_or_calling_analyzer(): void
    {
        $this->mockAnalyzerNotCalled();

        $this->postPreflight($this->createUser(), $this->preflightIdentity())
            ->assertOk()
            ->assertExactJson([
                'trial_id' => 'env-a-p010-r02-a01',
                'available' => true,
            ]);

        $this->assertDirectoryDoesNotExist($this->measurementPath);
    }

    public function test_preflight_rejects_matching_jsonl_record_without_modification(): void
    {
        $this->mockAnalyzerNotCalled();
        File::ensureDirectoryExists(dirname($this->jsonlPath()));
        $jsonl = "{\"trial_id\":\"env-a-p010-r02-a01\"}\n";
        file_put_contents($this->jsonlPath(), $jsonl);

        $this->postPreflight($this->createUser(), $this->preflightIdentity())
            ->assertStatus(409)
            ->assertJson([
                'trial_id' => 'env-a-p010-r02-a01',
                'available' => false,
                'invalid_reason' => 'duplicate_trial_id',
            ]);

        $this->assertSame($jsonl, file_get_contents($this->jsonlPath()));
        $this->assertFileDoesNotExist($this->audioPath('env-a-p010-r02-a01'));
    }

    public function test_preflight_rejects_matching_webm_without_modification(): void
    {
        $this->mockAnalyzerNotCalled();
        $audioPath = $this->audioPath('env-a-p010-r02-a01');
        File::ensureDirectoryExists(dirname($audioPath));
        file_put_contents($audioPath, 'existing-webm');

        $this->postPreflight($this->createUser(), $this->preflightIdentity())
            ->assertStatus(409)
            ->assertJson([
                'trial_id' => 'env-a-p010-r02-a01',
                'available' => false,
                'invalid_reason' => 'duplicate_trial_id',
            ]);

        $this->assertSame('existing-webm', file_get_contents($audioPath));
        $this->assertFileDoesNotExist($this->jsonlPath());
    }

    public function test_preflight_fails_closed_for_malformed_jsonl_without_modification(): void
    {
        $this->mockAnalyzerNotCalled();
        File::ensureDirectoryExists(dirname($this->jsonlPath()));
        $user = $this->createUser();

        foreach (["{malformed-json}\n", "123\n"] as $jsonl) {
            file_put_contents($this->jsonlPath(), $jsonl);

            $this->postPreflight($user, $this->preflightIdentity())
                ->assertStatus(500)
                ->assertJsonPath('invalid_reason', 'storage_failed')
                ->assertJsonMissingPath('exception');

            $this->assertSame($jsonl, file_get_contents($this->jsonlPath()));
        }
    }

    public function test_preflight_validation_failure_creates_no_artifacts(): void
    {
        $this->mockAnalyzerNotCalled();
        $identity = $this->preflightIdentity();
        $identity['run_number'] = 6;

        $this->postPreflight($this->createUser(), $identity)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The trial identity is invalid.');

        $this->assertDirectoryDoesNotExist($this->measurementPath);
    }

    public function test_each_allowed_profile_can_be_stored(): void
    {
        $this->mockAnalyzer(times: 5);
        $user = $this->createUser();

        foreach ([10, 40, 60, 90, 120] as $attempt => $profile) {
            $metadata = $this->validMetadata($profile, $attempt + 1);

            $this->postTrial($user, $metadata, $this->fakeWebm())
                ->assertCreated()
                ->assertJsonPath('valid', true);
        }

        $this->assertCount(5, $this->jsonlRecords());
    }

    public function test_invalid_profile_is_rejected_without_creating_a_record(): void
    {
        $metadata = $this->validMetadata(60);
        $metadata['profile_seconds'] = 30;

        $this->postTrial($this->createUser(), $metadata, $this->fakeWebm())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_seconds');

        $this->assertFileDoesNotExist($this->jsonlPath());
    }

    public function test_duplicate_trial_id_is_rejected_with_http_409_without_overwrite(): void
    {
        $this->mockAnalyzer();
        $user = $this->createUser();
        $metadata = $this->validMetadata();

        $this->postTrial($user, $metadata, $this->fakeWebm())->assertCreated();
        $originalAudio = file_get_contents($this->audioPath('env-a-p060-r01-a01'));

        $this->postTrial($user, $metadata, $this->fakeWebm())
            ->assertStatus(409)
            ->assertJsonPath('invalid_reason', 'duplicate_trial_id');

        $this->assertCount(1, $this->jsonlRecords());
        $this->assertSame($originalAudio, file_get_contents($this->audioPath('env-a-p060-r01-a01')));
    }

    public function test_valid_trial_creates_server_named_webm_and_jsonl_record(): void
    {
        $this->mockAnalyzer([
            'webm_duration_seconds' => 60.173456,
            'wav_duration_seconds' => 60.17125,
            'duration_method_difference_seconds' => 0.002206,
        ]);
        $user = $this->createUser();

        $this->postTrial($user, $this->validMetadata(), $this->fakeWebm())
            ->assertCreated()
            ->assertJsonPath('trial_id', 'env-a-p060-r01-a01')
            ->assertJsonPath('valid', true);

        $this->assertFileExists($this->audioPath('env-a-p060-r01-a01'));
        $records = $this->jsonlRecords();
        $this->assertCount(1, $records);
        $this->assertSame('t000-06-raw-v1', $records[0]['schema_version']);
        $this->assertSame('raw-audio/env-a-p060-r01-a01.webm', $records[0]['audio_relative_path']);
        $this->assertEqualsWithDelta(60.173456, $records[0]['webm_duration_seconds'], 0.0000001);
        $this->assertEqualsWithDelta(0.173456, $records[0]['total_overrun_seconds'], 0.0000001);
        $this->assertDirectoryExists($this->measurementPath.DIRECTORY_SEPARATOR.'temporary');
        $this->assertCount(0, File::files($this->measurementPath.DIRECTORY_SEPARATOR.'temporary'));
    }

    public function test_invalid_metadata_only_trial_is_preserved_in_jsonl(): void
    {
        $metadata = $this->validMetadata();
        $metadata['recording_started_ms'] = null;
        $metadata['stop_requested_ms'] = null;
        $metadata['recorder_stop_called_ms'] = null;
        $metadata['last_dataavailable_ms'] = null;
        $metadata['stop_event_ms'] = null;
        $metadata['blob_completed_ms'] = null;
        $metadata['blob_size_bytes'] = null;
        $metadata['client_invalid_reason'] = 'media_recorder_unsupported';
        $metadata['recorder_error'] = 'MediaRecorderUnsupported';

        $this->postTrial($this->createUser(), $metadata)
            ->assertCreated()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('invalid_reason', 'media_recorder_unsupported');

        $record = $this->jsonlRecords()[0];
        $this->assertFalse($record['valid']);
        $this->assertSame('media_recorder_unsupported', $record['invalid_reason']);
        $this->assertNull($record['audio_relative_path']);
        $this->assertNull($record['webm_duration_seconds']);
    }

    public function test_server_marks_timestamp_order_and_visibility_changes_invalid(): void
    {
        $this->mockAnalyzer(times: 2);
        $user = $this->createUser();
        $timestampMetadata = $this->validMetadata(overrides: [
            'last_dataavailable_ms' => 100,
        ]);
        $visibilityMetadata = $this->validMetadata(attempt: 2, overrides: [
            'visibility_change_count' => 1,
        ]);

        $this->postTrial($user, $timestampMetadata, $this->fakeWebm())
            ->assertCreated()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('invalid_reason', 'timestamp_order_invalid');

        $this->postTrial($user, $visibilityMetadata, $this->fakeWebm())
            ->assertCreated()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('invalid_reason', 'tab_visibility_changed');
    }

    public function test_empty_blob_is_preserved_as_metadata_only_invalid_trial(): void
    {
        $metadata = $this->validMetadata(overrides: ['blob_size_bytes' => 0]);

        $this->postTrial($this->createUser(), $metadata)
            ->assertCreated()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('invalid_reason', 'blob_empty');

        $record = $this->jsonlRecords()[0];
        $this->assertSame(0, $record['blob_size_bytes']);
        $this->assertNull($record['audio_relative_path']);
    }

    public function test_jsonl_excludes_identity_and_absolute_path_fields(): void
    {
        $this->mockAnalyzer();
        $user = $this->createUser();

        $this->postTrial($user, $this->validMetadata(), $this->fakeWebm())->assertCreated();

        $rawJsonl = file_get_contents($this->jsonlPath());
        $record = $this->jsonlRecords()[0];

        foreach (['user_id', 'user_name', 'email', 'session_id', 'submission_id', 'transcript'] as $key) {
            $this->assertArrayNotHasKey($key, $record);
        }

        $this->assertStringNotContainsString($user->email, $rawJsonl);
        $this->assertStringNotContainsString(str_replace('\\', '/', $this->measurementPath), str_replace('\\', '/', $rawJsonl));
        $this->assertStringNotContainsString('ffmpeg -', $rawJsonl);
        $this->assertStringNotContainsString('ffprobe -', $rawJsonl);
    }

    public function test_jsonl_append_failure_rolls_back_partial_line_and_only_the_new_webm_then_allows_retry(): void
    {
        $this->mockAnalyzer(times: 3);
        $analyzer = $this->app->make(T00006AudioAnalyzer::class);
        $controller = new class($analyzer) extends T00006MediaRecorderController
        {
            private bool $failNextAppend = false;

            public function failNextAppend(): void
            {
                $this->failNextAppend = true;
            }

            protected function appendJsonlRecord($handle, array $record): void
            {
                if (! $this->failNextAppend) {
                    parent::appendJsonlRecord($handle, $record);

                    return;
                }

                $this->failNextAppend = false;
                fseek($handle, 0, SEEK_END);
                fwrite($handle, '{"partial_trial":');
                fflush($handle);

                throw new RuntimeException('simulated_jsonl_failure');
            }
        };
        $this->app->instance(T00006MediaRecorderController::class, $controller);

        $user = $this->createUser();
        $trialAMetadata = $this->validMetadata(attempt: 1);
        $trialBMetadata = $this->validMetadata(attempt: 2);
        $trialAId = 'env-a-p060-r01-a01';
        $trialBId = 'env-a-p060-r01-a02';

        $this->postTrial($user, $trialAMetadata, $this->fakeWebm())->assertCreated();
        $jsonlAfterTrialA = file_get_contents($this->jsonlPath());
        $trialAAudio = file_get_contents($this->audioPath($trialAId));

        $controller->failNextAppend();

        $this->postTrial($user, $trialBMetadata, $this->fakeWebm())
            ->assertStatus(500)
            ->assertJsonPath('invalid_reason', 'storage_failed');

        $this->assertSame($jsonlAfterTrialA, file_get_contents($this->jsonlPath()));
        $this->assertSame($trialAAudio, file_get_contents($this->audioPath($trialAId)));
        $this->assertFileDoesNotExist($this->audioPath($trialBId));
        $this->assertCount(1, $this->jsonlRecords());
        $this->assertSame($trialAId, $this->jsonlRecords()[0]['trial_id']);

        $this->postTrial($user, $trialBMetadata, $this->fakeWebm())
            ->assertCreated()
            ->assertJsonPath('trial_id', $trialBId);

        $this->assertFileExists($this->audioPath($trialBId));
        $this->assertSame([$trialAId, $trialBId], array_column($this->jsonlRecords(), 'trial_id'));
    }

    private function mockAnalyzer(?array $result = null, int $times = 1): void
    {
        $result ??= [
            'webm_duration_seconds' => 60.2,
            'wav_duration_seconds' => 60.19,
            'duration_method_difference_seconds' => 0.01,
        ];

        $this->mock(T00006AudioAnalyzer::class, function (MockInterface $mock) use ($result, $times) {
            $mock->shouldReceive('analyze')
                ->times($times)
                ->andReturn($result);
        });
    }

    private function mockAnalyzerNotCalled(): void
    {
        $this->mock(T00006AudioAnalyzer::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('analyze');
        });
    }

    private function createUser(bool $verified = true): User
    {
        $user = User::query()->create([
            'name' => 'T000-06 Test User',
            'email' => uniqid('t00006_', true).'@example.com',
            'password' => 'password',
        ]);

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validMetadata(int $profile = 60, int $attempt = 1, array $overrides = []): array
    {
        $stopRequested = ($profile * 1000) + 50.25;

        return array_merge([
            'environment_id' => 'env-a',
            'profile_seconds' => $profile,
            'run_number' => 1,
            'attempt_number' => $attempt,
            'browser_user_agent' => 'T00006 Test Browser',
            'browser_platform' => 'Test Platform',
            'browser_language' => 'ja-JP',
            'requested_mime_type' => 'audio/webm;codecs=opus',
            'actual_mime_type' => 'audio/webm;codecs=opus',
            'recording_started_ms' => 0,
            'stop_requested_ms' => $stopRequested,
            'recorder_stop_called_ms' => $stopRequested + 0.25,
            'last_dataavailable_ms' => $stopRequested + 20.5,
            'stop_event_ms' => $stopRequested + 21.0,
            'blob_completed_ms' => $stopRequested + 21.2,
            'blob_size_bytes' => 1024,
            'started_visibility_state' => 'visible',
            'ended_visibility_state' => 'visible',
            'visibility_change_count' => 0,
            'recorder_error' => null,
            'client_invalid_reason' => null,
        ], $overrides);
    }

    private function fakeWebm(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'client-name.webm',
            str_repeat('webm-test-data-', 80),
        );
    }

    private function postTrial(User $user, array $metadata, ?UploadedFile $audio = null): TestResponse
    {
        $payload = [
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ];

        if ($audio !== null) {
            $payload['audio_file'] = $audio;
        }

        return $this->actingAs($user)->post(
            route('verification.t000-06.media-recorder.store'),
            $payload,
            ['Accept' => 'application/json'],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function preflightIdentity(array $overrides = []): array
    {
        return array_merge([
            'environment_id' => 'env-a',
            'profile_seconds' => 10,
            'run_number' => 2,
            'attempt_number' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function postPreflight(User $user, array $identity): TestResponse
    {
        return $this->actingAs($user)->postJson(
            route('verification.t000-06.media-recorder.preflight'),
            $identity,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonlRecords(): array
    {
        $lines = file($this->jsonlPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return array_map(
            static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            $lines,
        );
    }

    private function jsonlPath(): string
    {
        return $this->measurementPath.DIRECTORY_SEPARATOR.'raw'.DIRECTORY_SEPARATOR.'measurements.jsonl';
    }

    private function audioPath(string $trialId): string
    {
        return $this->measurementPath.DIRECTORY_SEPARATOR.'raw-audio'.DIRECTORY_SEPARATOR.$trialId.'.webm';
    }
}
