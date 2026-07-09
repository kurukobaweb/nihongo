<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FeatureFlagReflectionTest extends TestCase
{
    public function test_speech_feature_flags_are_off_by_default(): void
    {
        $this->assertFalse((bool) config('features.speech_pronunciation_assessment_enabled'));
        $this->assertFalse((bool) config('features.speech_fluency_assessment_enabled'));
    }

    public function test_speech_feature_flags_follow_environment_values_after_boot(): void
    {
        $this->withFeatureFlagEnvironment('true', 'true', function (): void {
            Env::enablePutenv();
            $this->refreshApplication();

            $this->assertTrue((bool) config('features.speech_pronunciation_assessment_enabled'));
            $this->assertTrue((bool) config('features.speech_fluency_assessment_enabled'));
        });

        $this->withFeatureFlagEnvironment('false', 'false', function (): void {
            Env::enablePutenv();
            $this->refreshApplication();

            $this->assertFalse((bool) config('features.speech_pronunciation_assessment_enabled'));
            $this->assertFalse((bool) config('features.speech_fluency_assessment_enabled'));
        });
    }

    public function test_running_application_reads_new_feature_flags_after_restart(): void
    {
        $this->withFeatureFlagEnvironment('false', 'false', function (): void {
            Env::enablePutenv();
            $this->refreshApplication();

            $this->assertFalse((bool) config('features.speech_pronunciation_assessment_enabled'));
            $this->assertFalse((bool) config('features.speech_fluency_assessment_enabled'));

            $this->setEnvironmentValue('SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED', 'true');
            $this->setEnvironmentValue('SPEECH_FLUENCY_ASSESSMENT_ENABLED', 'true');

            // A long-running process keeps its boot-time config until it is restarted.
            $this->assertFalse((bool) config('features.speech_pronunciation_assessment_enabled'));
            $this->assertFalse((bool) config('features.speech_fluency_assessment_enabled'));

            $this->refreshApplication();

            $this->assertTrue((bool) config('features.speech_pronunciation_assessment_enabled'));
            $this->assertTrue((bool) config('features.speech_fluency_assessment_enabled'));
        });
    }

    public function test_config_cache_regeneration_uses_current_feature_flag_environment(): void
    {
        try {
            $this->withFeatureFlagEnvironment('true', 'true', function (): void {
                Artisan::call('config:clear');
                Artisan::call('config:cache');

                $cachedConfig = require $this->app->getCachedConfigPath();

                $this->assertTrue($cachedConfig['features']['speech_pronunciation_assessment_enabled']);
                $this->assertTrue($cachedConfig['features']['speech_fluency_assessment_enabled']);
            });

            $this->withFeatureFlagEnvironment('false', 'false', function (): void {
                Artisan::call('config:clear');
                Artisan::call('config:cache');

                $cachedConfig = require $this->app->getCachedConfigPath();

                $this->assertFalse($cachedConfig['features']['speech_pronunciation_assessment_enabled']);
                $this->assertFalse($cachedConfig['features']['speech_fluency_assessment_enabled']);
            });
        } finally {
            Artisan::call('config:clear');
        }
    }

    public function test_inertia_shared_features_are_boolean_cast_from_config(): void
    {
        config()->set('features.speech_pronunciation_assessment_enabled', '1');
        config()->set('features.speech_fluency_assessment_enabled', 0);

        $shared = (new HandleInertiaRequests())->share(Request::create('/'));

        $this->assertSame(true, $shared['features']['speech_pronunciation_assessment_enabled']);
        $this->assertSame(false, $shared['features']['speech_fluency_assessment_enabled']);
    }

    private function withFeatureFlagEnvironment(string $pronunciation, string $fluency, callable $callback): void
    {
        $previousPronunciation = getenv('SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED');
        $previousFluency = getenv('SPEECH_FLUENCY_ASSESSMENT_ENABLED');

        $this->setEnvironmentValue('SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED', $pronunciation);
        $this->setEnvironmentValue('SPEECH_FLUENCY_ASSESSMENT_ENABLED', $fluency);

        try {
            $callback();
        } finally {
            $this->setEnvironmentValue('SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED', $previousPronunciation);
            $this->setEnvironmentValue('SPEECH_FLUENCY_ASSESSMENT_ENABLED', $previousFluency);
            Env::enablePutenv();
            $this->refreshApplication();
        }
    }

    private function setEnvironmentValue(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
