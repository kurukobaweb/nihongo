<?php

namespace App\Services\Verification;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class T00006AudioAnalyzer
{
    /**
     * @return array{
     *     webm_duration_seconds: float,
     *     wav_duration_seconds: float,
     *     duration_method_difference_seconds: float
     * }
     */
    public function analyze(string $webmPath, string $wavPath): array
    {
        try {
            $webmDuration = $this->probeWebmDuration($webmPath);
            $this->convertToWav($webmPath, $wavPath);
            $wavDuration = $this->probeWavDuration($wavPath);

            return [
                'webm_duration_seconds' => $webmDuration,
                'wav_duration_seconds' => $wavDuration,
                'duration_method_difference_seconds' => self::durationDifference(
                    $webmDuration,
                    $wavDuration,
                ),
            ];
        } finally {
            if (is_file($wavPath) && ! unlink($wavPath)) {
                throw new RuntimeException('storage_failed');
            }
        }
    }

    public static function parseWebmDuration(string $json): float
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('ffprobe_failed', previous: $exception);
        }

        $duration = $payload['format']['duration'] ?? null;

        if (! is_numeric($duration)) {
            throw new RuntimeException('ffprobe_failed');
        }

        $seconds = (float) $duration;

        if (! is_finite($seconds) || $seconds <= 0) {
            throw new RuntimeException('ffprobe_failed');
        }

        return $seconds;
    }

    public static function parseWavDuration(string $json): float
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('wav_duration_failed', previous: $exception);
        }

        $stream = $payload['streams'][0] ?? null;
        $durationTs = $stream['duration_ts'] ?? null;
        $timeBase = $stream['time_base'] ?? null;

        if ((! is_int($durationTs) && ! is_string($durationTs)) || ! is_string($timeBase)) {
            throw new RuntimeException('wav_duration_failed');
        }

        $durationTsString = (string) $durationTs;

        if (preg_match('/^[0-9]+$/', $durationTsString) !== 1 || (int) $durationTsString <= 0) {
            throw new RuntimeException('wav_duration_failed');
        }

        if (preg_match('/^([0-9]+)\/([0-9]+)$/', $timeBase, $matches) !== 1) {
            throw new RuntimeException('wav_duration_failed');
        }

        $numerator = (int) $matches[1];
        $denominator = (int) $matches[2];

        if ($numerator <= 0 || $denominator <= 0) {
            throw new RuntimeException('wav_duration_failed');
        }

        $seconds = (int) $durationTsString * ($numerator / $denominator);

        if (! is_finite($seconds) || $seconds <= 0) {
            throw new RuntimeException('wav_duration_failed');
        }

        return $seconds;
    }

    public static function durationDifference(float $webmDuration, float $wavDuration): float
    {
        return abs($webmDuration - $wavDuration);
    }

    /**
     * @return array{
     *     stop_request_delay_seconds: ?float,
     *     stop_call_delay_seconds: ?float,
     *     recorder_tail_seconds: ?float,
     *     total_overrun_seconds: ?float
     * }
     */
    public static function calculateMetrics(
        int $profileSeconds,
        ?float $stopRequestedMs,
        ?float $recorderStopCalledMs,
        ?float $webmDurationSeconds,
    ): array {
        return [
            'stop_request_delay_seconds' => $stopRequestedMs === null
                ? null
                : ($stopRequestedMs / 1000) - $profileSeconds,
            'stop_call_delay_seconds' => $stopRequestedMs === null || $recorderStopCalledMs === null
                ? null
                : ($recorderStopCalledMs - $stopRequestedMs) / 1000,
            'recorder_tail_seconds' => $stopRequestedMs === null || $webmDurationSeconds === null
                ? null
                : $webmDurationSeconds - ($stopRequestedMs / 1000),
            'total_overrun_seconds' => $webmDurationSeconds === null
                ? null
                : $webmDurationSeconds - $profileSeconds,
        ];
    }

    protected function probeWebmDuration(string $webmPath): float
    {
        $output = $this->runProcess([
            (string) config('t000-06.ffprobe_binary', 'ffprobe'),
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'json',
            $webmPath,
        ], 'ffprobe_failed');

        return self::parseWebmDuration($output);
    }

    protected function convertToWav(string $webmPath, string $wavPath): void
    {
        $this->runProcess([
            (string) config('t000-06.ffmpeg_binary', 'ffmpeg'),
            '-y',
            '-v',
            'error',
            '-i',
            $webmPath,
            '-ac',
            '1',
            '-ar',
            '16000',
            '-c:a',
            'pcm_s16le',
            $wavPath,
        ], 'ffmpeg_failed');
    }

    protected function probeWavDuration(string $wavPath): float
    {
        $output = $this->runProcess([
            (string) config('t000-06.ffprobe_binary', 'ffprobe'),
            '-v',
            'error',
            '-select_streams',
            'a:0',
            '-show_entries',
            'stream=duration_ts,time_base',
            '-of',
            'json',
            $wavPath,
        ], 'wav_duration_failed');

        return self::parseWavDuration($output);
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, string $failureReason): string
    {
        $process = new Process($command);
        $process->setTimeout((float) config('t000-06.process_timeout_seconds', 30));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException($failureReason, previous: $exception);
        }

        $stderr = trim($process->getErrorOutput());

        if (! $process->isSuccessful() || $stderr !== '') {
            // stderr content is intentionally not persisted; only the stable reason is written to JSONL.
            throw new RuntimeException($failureReason);
        }

        return $process->getOutput();
    }
}
