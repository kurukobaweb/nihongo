<?php

namespace Tests\Unit\Verification;

use App\Services\Verification\T00006AudioAnalyzer;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class T00006AudioAnalyzerTest extends TestCase
{
    public function test_webm_ffprobe_json_duration_is_parsed_without_rounding(): void
    {
        $duration = T00006AudioAnalyzer::parseWebmDuration(json_encode([
            'format' => ['duration' => '60.173456'],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(60.173456, $duration);
    }

    public function test_wav_duration_uses_duration_ts_multiplied_by_fractional_time_base(): void
    {
        $duration = T00006AudioAnalyzer::parseWavDuration(json_encode([
            'streams' => [[
                'duration_ts' => 160800,
                'time_base' => '1/16000',
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->assertEqualsWithDelta(10.05, $duration, 0.000000001);
    }

    public function test_duration_difference_and_measurement_formulas_preserve_precision(): void
    {
        $this->assertEqualsWithDelta(
            0.002206,
            T00006AudioAnalyzer::durationDifference(60.173456, 60.17125),
            0.000000001,
        );

        $metrics = T00006AudioAnalyzer::calculateMetrics(60, 60050.0, 60050.3, 60.2);

        $this->assertEqualsWithDelta(0.05, $metrics['stop_request_delay_seconds'], 0.000000001);
        $this->assertEqualsWithDelta(0.0003, $metrics['stop_call_delay_seconds'], 0.000000001);
        $this->assertEqualsWithDelta(0.15, $metrics['recorder_tail_seconds'], 0.000000001);
        $this->assertEqualsWithDelta(0.2, $metrics['total_overrun_seconds'], 0.000000001);
    }

    public function test_missing_metrics_inputs_produce_null_instead_of_fallback_values(): void
    {
        $metrics = T00006AudioAnalyzer::calculateMetrics(60, null, null, null);

        $this->assertSame([
            'stop_request_delay_seconds' => null,
            'stop_call_delay_seconds' => null,
            'recorder_tail_seconds' => null,
            'total_overrun_seconds' => null,
        ], $metrics);
    }

    public function test_invalid_webm_duration_values_are_rejected(): void
    {
        foreach ([null, 'not-a-number', '0', '-1'] as $value) {
            try {
                T00006AudioAnalyzer::parseWebmDuration(json_encode([
                    'format' => ['duration' => $value],
                ], JSON_THROW_ON_ERROR));
                $this->fail('Invalid WebM duration was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('ffprobe_failed', $exception->getMessage());
            }
        }
    }

    public function test_invalid_duration_ts_or_time_base_is_rejected_without_fallback(): void
    {
        $invalidStreams = [
            ['duration_ts' => null, 'time_base' => '1/16000'],
            ['duration_ts' => 16000, 'time_base' => '0/16000'],
            ['duration_ts' => 16000, 'time_base' => '0.0000625'],
            ['duration_ts' => -1, 'time_base' => '1/16000'],
        ];

        foreach ($invalidStreams as $stream) {
            try {
                T00006AudioAnalyzer::parseWavDuration(json_encode([
                    'streams' => [$stream],
                ], JSON_THROW_ON_ERROR));
                $this->fail('Invalid WAV duration data was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('wav_duration_failed', $exception->getMessage());
            }
        }
    }

    public function test_temporary_wav_is_deleted_when_analysis_fails(): void
    {
        $directory = storage_path('framework/testing/t000-06-analyzer-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        $wavPath = $directory.DIRECTORY_SEPARATOR.'temporary.wav';
        file_put_contents($wavPath, 'temporary');

        $analyzer = new class extends T00006AudioAnalyzer
        {
            protected function probeWebmDuration(string $webmPath): float
            {
                throw new RuntimeException('ffprobe_failed');
            }
        };

        try {
            $analyzer->analyze('unused.webm', $wavPath);
            $this->fail('Expected analyzer failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ffprobe_failed', $exception->getMessage());
        } finally {
            $this->assertFileDoesNotExist($wavPath);
            File::deleteDirectory($directory);
        }
    }
}
