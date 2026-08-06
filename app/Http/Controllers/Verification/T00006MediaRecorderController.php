<?php

namespace App\Http\Controllers\Verification;

use App\Http\Controllers\Controller;
use App\Services\Verification\T00006AudioAnalyzer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use RuntimeException;
use Throwable;

class T00006MediaRecorderController extends Controller
{
    private const PROFILES = [10, 40, 60, 90, 120];

    private const TRIAL_IDENTITY_KEYS = [
        'environment_id',
        'profile_seconds',
        'run_number',
        'attempt_number',
    ];

    private const DEFAULT_TRIAL = [
        'environment_id' => 'env-a',
        'profile_seconds' => 10,
        'run_number' => 1,
        'attempt_number' => 1,
    ];

    private const SCHEMA_VERSION = 't000-06-raw-v1';

    private const ANALYSIS_FAILURE_REASONS = [
        'ffprobe_failed',
        'ffmpeg_failed',
        'wav_duration_failed',
        'storage_failed',
    ];

    private const CLIENT_FAILURE_REASONS = [
        'microphone_permission_denied',
        'media_recorder_unsupported',
        'media_recorder_error',
    ];

    public function __construct(private readonly T00006AudioAnalyzer $analyzer) {}

    public function show(Request $request): Response
    {
        $initialTrial = $request->hasAny(self::TRIAL_IDENTITY_KEYS)
            ? $this->validatedTrialIdentity($request->query())
            : self::DEFAULT_TRIAL;

        return Inertia::render('Verification/T00006MediaRecorder', [
            'profiles' => self::PROFILES,
            'initialTrial' => $initialTrial,
            'initialTrialId' => $this->trialId($initialTrial),
        ]);
    }

    public function preflight(Request $request): JsonResponse
    {
        $identity = $this->validatedTrialIdentity($request->all());
        $trialId = $this->trialId($identity);

        try {
            $paths = $this->trialPaths($trialId);

            if (is_file($paths['audio_absolute'])) {
                return $this->duplicatePreflightResponse($trialId);
            }

            if (! is_file($paths['jsonl_absolute'])) {
                return response()->json([
                    'trial_id' => $trialId,
                    'available' => true,
                ]);
            }

            $handle = @fopen($paths['jsonl_absolute'], 'rb');

            if ($handle === false || ! flock($handle, LOCK_SH)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }

                throw new RuntimeException('storage_failed');
            }

            try {
                $duplicate = $this->jsonlContainsTrial($handle, $trialId);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }

            if ($duplicate) {
                return $this->duplicatePreflightResponse($trialId);
            }

            return response()->json([
                'trial_id' => $trialId,
                'available' => true,
            ]);
        } catch (Throwable) {
            return response()->json([
                'message' => 'The trial availability could not be checked.',
                'invalid_reason' => 'storage_failed',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'audio_file' => [
                'nullable',
                'file',
                'max:'.(int) config('t000-06.max_audio_kilobytes', 20480),
            ],
            'metadata' => ['required', 'string'],
        ]);

        $metadata = $this->validatedMetadata($request->string('metadata')->toString());
        $trialId = $this->trialId($metadata);
        $paths = $this->preparePaths($trialId);

        $handle = @fopen($paths['jsonl_absolute'], 'c+b');

        if ($handle === false || ! flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return response()->json([
                'message' => 'The measurement record could not be stored.',
                'invalid_reason' => 'storage_failed',
            ], 500);
        }

        $jsonlSizeBeforeAppend = null;
        $mayDeleteRequestAudio = false;

        try {
            if ($this->jsonlContainsTrial($handle, $trialId) || is_file($paths['audio_absolute'])) {
                return response()->json([
                    'message' => 'The trial ID already exists.',
                    'trial_id' => $trialId,
                    'invalid_reason' => 'duplicate_trial_id',
                ], 409);
            }

            $jsonlSizeBeforeAppend = $this->jsonlSize($handle);
            $mayDeleteRequestAudio = true;

            $record = $this->buildRecord(
                $metadata,
                $trialId,
                $request->file('audio_file'),
                $paths,
            );
            $this->appendJsonlRecord($handle, $record);

            return response()->json([
                'trial_id' => $record['trial_id'],
                'valid' => $record['valid'],
                'invalid_reason' => $record['invalid_reason'],
                'webm_duration_seconds' => $record['webm_duration_seconds'],
                'wav_duration_seconds' => $record['wav_duration_seconds'],
                'duration_method_difference_seconds' => $record['duration_method_difference_seconds'],
                'stop_request_delay_seconds' => $record['stop_request_delay_seconds'],
                'stop_call_delay_seconds' => $record['stop_call_delay_seconds'],
                'recorder_tail_seconds' => $record['recorder_tail_seconds'],
                'total_overrun_seconds' => $record['total_overrun_seconds'],
                'audio_relative_path' => $record['audio_relative_path'],
            ], 201);
        } catch (Throwable) {
            try {
                $this->rollbackJsonl($handle, $jsonlSizeBeforeAppend);
            } catch (Throwable) {
                // The response remains the same sanitized storage failure.
            }

            try {
                $this->deleteRequestAudio($mayDeleteRequestAudio, $paths['audio_absolute']);
            } catch (Throwable) {
                // The response remains the same sanitized storage failure.
            }

            return response()->json([
                'message' => 'The measurement record could not be stored.',
                'invalid_reason' => 'storage_failed',
            ], 500);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMetadata(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(422, 'The metadata field must contain valid JSON.');
        }

        if (! is_array($decoded)) {
            abort(422, 'The metadata field must contain a JSON object.');
        }

        $rules = array_merge(self::trialIdentityRules(), [
            'browser_user_agent' => ['nullable', 'string', 'max:1024'],
            'browser_platform' => ['nullable', 'string', 'max:255'],
            'browser_language' => ['nullable', 'string', 'max:64'],
            'requested_mime_type' => ['nullable', 'string', 'max:255'],
            'actual_mime_type' => ['nullable', 'string', 'max:255'],
            'recording_started_ms' => ['nullable', 'numeric'],
            'stop_requested_ms' => ['nullable', 'numeric'],
            'recorder_stop_called_ms' => ['nullable', 'numeric'],
            'last_dataavailable_ms' => ['nullable', 'numeric'],
            'stop_event_ms' => ['nullable', 'numeric'],
            'blob_completed_ms' => ['nullable', 'numeric'],
            'blob_size_bytes' => ['nullable', 'integer', 'min:0'],
            'started_visibility_state' => ['nullable', 'string', 'max:32'],
            'ended_visibility_state' => ['nullable', 'string', 'max:32'],
            'visibility_change_count' => ['nullable', 'integer', 'min:0'],
            'recorder_error' => ['nullable', 'string', 'max:255'],
            'client_invalid_reason' => ['nullable', 'string', Rule::in(self::CLIENT_FAILURE_REASONS)],
        ]);

        $validated = Validator::make($decoded, $rules)->validate();

        return array_merge(array_fill_keys([
            'browser_user_agent',
            'browser_platform',
            'browser_language',
            'requested_mime_type',
            'actual_mime_type',
            'recording_started_ms',
            'stop_requested_ms',
            'recorder_stop_called_ms',
            'last_dataavailable_ms',
            'stop_event_ms',
            'blob_completed_ms',
            'blob_size_bytes',
            'started_visibility_state',
            'ended_visibility_state',
            'visibility_change_count',
            'recorder_error',
            'client_invalid_reason',
        ], null), $validated);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{environment_id: string, profile_seconds: int, run_number: int, attempt_number: int}
     */
    private function validatedTrialIdentity(array $input): array
    {
        $validator = Validator::make($input, self::trialIdentityRules());

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'The trial identity is invalid.',
                'errors' => $validator->errors(),
            ], 422));
        }

        $validated = $validator->validated();

        return [
            'environment_id' => (string) $validated['environment_id'],
            'profile_seconds' => (int) $validated['profile_seconds'],
            'run_number' => (int) $validated['run_number'],
            'attempt_number' => (int) $validated['attempt_number'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function trialIdentityRules(): array
    {
        return [
            'environment_id' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9-]+$/'],
            'profile_seconds' => ['required', 'integer', Rule::in(self::PROFILES)],
            'run_number' => ['required', 'integer', 'between:1,5'],
            'attempt_number' => ['required', 'integer', 'between:1,99'],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function trialId(array $metadata): string
    {
        return sprintf(
            '%s-p%03d-r%02d-a%02d',
            $metadata['environment_id'],
            $metadata['profile_seconds'],
            $metadata['run_number'],
            $metadata['attempt_number'],
        );
    }

    /**
     * @return array{
     *     audio_absolute: string,
     *     audio_relative: string,
     *     jsonl_absolute: string,
     *     wav_absolute: string
     * }
     */
    private function trialPaths(string $trialId): array
    {
        $basePath = rtrim((string) config('t000-06.base_path'), '\\/');

        if ($basePath === '') {
            throw new RuntimeException('storage_failed');
        }

        $rawAudioDirectory = $basePath.DIRECTORY_SEPARATOR.'raw-audio';
        $rawDirectory = $basePath.DIRECTORY_SEPARATOR.'raw';
        $temporaryDirectory = $basePath.DIRECTORY_SEPARATOR.'temporary';

        return [
            'audio_absolute' => $rawAudioDirectory.DIRECTORY_SEPARATOR.$trialId.'.webm',
            'audio_relative' => 'raw-audio/'.$trialId.'.webm',
            'jsonl_absolute' => $rawDirectory.DIRECTORY_SEPARATOR.'measurements.jsonl',
            'wav_absolute' => $temporaryDirectory.DIRECTORY_SEPARATOR.$trialId.'.wav',
        ];
    }

    /**
     * @return array{
     *     audio_absolute: string,
     *     audio_relative: string,
     *     jsonl_absolute: string,
     *     wav_absolute: string
     * }
     */
    private function preparePaths(string $trialId): array
    {
        $paths = $this->trialPaths($trialId);

        File::ensureDirectoryExists(dirname($paths['audio_absolute']));
        File::ensureDirectoryExists(dirname($paths['jsonl_absolute']));
        File::ensureDirectoryExists(dirname($paths['wav_absolute']));

        return $paths;
    }

    /**
     * @param  resource  $handle
     */
    private function jsonlContainsTrial($handle, string $trialId): bool
    {
        rewind($handle);

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('storage_failed', previous: $exception);
            }

            if (! is_array($record) || ! is_string($record['trial_id'] ?? null)) {
                throw new RuntimeException('storage_failed');
            }

            if ($record['trial_id'] === $trialId) {
                return true;
            }
        }

        if (! feof($handle)) {
            throw new RuntimeException('storage_failed');
        }

        return false;
    }

    private function duplicatePreflightResponse(string $trialId): JsonResponse
    {
        return response()->json([
            'message' => 'The trial ID already exists.',
            'trial_id' => $trialId,
            'available' => false,
            'invalid_reason' => 'duplicate_trial_id',
        ], 409);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{audio_absolute: string, audio_relative: string, jsonl_absolute: string, wav_absolute: string}  $paths
     * @return array<string, mixed>
     */
    private function buildRecord(
        array $metadata,
        string $trialId,
        ?UploadedFile $audioFile,
        array $paths,
    ): array {
        $audioStored = false;
        $storageFailed = false;
        $blobSizeBytes = $audioFile === null && $metadata['blob_size_bytes'] === 0 ? 0 : null;

        if ($audioFile !== null) {
            try {
                $audioFile->move(dirname($paths['audio_absolute']), basename($paths['audio_absolute']));
                $audioStored = is_file($paths['audio_absolute']);
                $blobSizeBytes = $audioStored ? filesize($paths['audio_absolute']) : null;
                $storageFailed = ! $audioStored || $blobSizeBytes === false;
                $blobSizeBytes = $blobSizeBytes === false ? null : $blobSizeBytes;
            } catch (Throwable) {
                $audioStored = is_file($paths['audio_absolute']);
                $partialSize = $audioStored ? filesize($paths['audio_absolute']) : false;
                $blobSizeBytes = is_int($partialSize) ? $partialSize : null;
                $storageFailed = true;
            }
        }

        $analysis = null;
        $analysisFailure = null;

        if ($audioStored && is_int($blobSizeBytes) && $blobSizeBytes > 0) {
            try {
                $analysis = $this->analyzer->analyze($paths['audio_absolute'], $paths['wav_absolute']);
                $analysisFailure = $this->analysisResultFailure($analysis);
            } catch (RuntimeException $exception) {
                $analysisFailure = in_array($exception->getMessage(), self::ANALYSIS_FAILURE_REASONS, true)
                    ? $exception->getMessage()
                    : 'ffprobe_failed';
            } catch (Throwable) {
                $analysisFailure = 'ffprobe_failed';
            }
        }

        $webmDuration = $analysisFailure === null
            ? $this->nullableFinitePositive($analysis['webm_duration_seconds'] ?? null)
            : null;
        $wavDuration = $analysisFailure === null
            ? $this->nullableFinitePositive($analysis['wav_duration_seconds'] ?? null)
            : null;
        $durationDifference = $webmDuration !== null && $wavDuration !== null
            ? T00006AudioAnalyzer::durationDifference($webmDuration, $wavDuration)
            : null;

        $timestamps = [
            'recording_started_ms' => $this->nullableFloat($metadata['recording_started_ms']),
            'stop_requested_ms' => $this->nullableFloat($metadata['stop_requested_ms']),
            'recorder_stop_called_ms' => $this->nullableFloat($metadata['recorder_stop_called_ms']),
            'last_dataavailable_ms' => $this->nullableFloat($metadata['last_dataavailable_ms']),
            'stop_event_ms' => $this->nullableFloat($metadata['stop_event_ms']),
            'blob_completed_ms' => $this->nullableFloat($metadata['blob_completed_ms']),
        ];

        $invalidReason = $this->invalidReason(
            $metadata,
            $timestamps,
            $audioFile,
            $blobSizeBytes,
            $audioStored,
            $storageFailed,
            $analysisFailure,
            $webmDuration,
            $wavDuration,
        );
        $metrics = T00006AudioAnalyzer::calculateMetrics(
            (int) $metadata['profile_seconds'],
            $timestamps['stop_requested_ms'],
            $timestamps['recorder_stop_called_ms'],
            $webmDuration,
        );

        return array_merge([
            'schema_version' => self::SCHEMA_VERSION,
            'trial_id' => $trialId,
            'environment_id' => $metadata['environment_id'],
            'profile_seconds' => (int) $metadata['profile_seconds'],
            'run_number' => (int) $metadata['run_number'],
            'attempt_number' => (int) $metadata['attempt_number'],
            'browser_user_agent' => $metadata['browser_user_agent'],
            'browser_platform' => $metadata['browser_platform'],
            'browser_language' => $metadata['browser_language'],
            'requested_mime_type' => $metadata['requested_mime_type'],
            'actual_mime_type' => $metadata['actual_mime_type'],
            'recorder_error' => $metadata['recorder_error'],
        ], $timestamps, [
            'blob_size_bytes' => $blobSizeBytes,
            'webm_duration_seconds' => $webmDuration,
            'wav_duration_seconds' => $wavDuration,
            'duration_method_difference_seconds' => $durationDifference,
        ], $metrics, [
            'started_visibility_state' => $metadata['started_visibility_state'],
            'ended_visibility_state' => $metadata['ended_visibility_state'],
            'visibility_change_count' => $metadata['visibility_change_count'] === null
                ? null
                : (int) $metadata['visibility_change_count'],
            'valid' => $invalidReason === null,
            'invalid_reason' => $invalidReason,
            'audio_relative_path' => $audioStored ? $paths['audio_relative'] : null,
            'recorded_at_utc' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function analysisResultFailure(array $analysis): ?string
    {
        if ($this->nullableFinitePositive($analysis['webm_duration_seconds'] ?? null) === null) {
            return 'ffprobe_failed';
        }

        if ($this->nullableFinitePositive($analysis['wav_duration_seconds'] ?? null) === null) {
            return 'wav_duration_failed';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, ?float>  $timestamps
     */
    private function invalidReason(
        array $metadata,
        array $timestamps,
        ?UploadedFile $audioFile,
        int|false|null $blobSizeBytes,
        bool $audioStored,
        bool $storageFailed,
        ?string $analysisFailure,
        ?float $webmDuration,
        ?float $wavDuration,
    ): ?string {
        if (in_array($metadata['client_invalid_reason'], self::CLIENT_FAILURE_REASONS, true)) {
            return $metadata['client_invalid_reason'];
        }

        if (is_string($metadata['recorder_error']) && $metadata['recorder_error'] !== '') {
            return 'media_recorder_error';
        }

        if ($blobSizeBytes === 0) {
            return 'blob_empty';
        }

        if ($audioFile === null) {
            return 'blob_missing';
        }

        if ($storageFailed || ! $audioStored) {
            return 'storage_failed';
        }

        if (in_array(null, $timestamps, true)) {
            return 'timestamp_missing';
        }

        if (! $this->timestampsAreOrdered($timestamps)) {
            return 'timestamp_order_invalid';
        }

        if (
            $metadata['started_visibility_state'] !== 'visible'
            || $metadata['ended_visibility_state'] !== 'visible'
            || $metadata['visibility_change_count'] === null
            || (int) $metadata['visibility_change_count'] !== 0
        ) {
            return 'tab_visibility_changed';
        }

        if ($analysisFailure !== null) {
            return $analysisFailure;
        }

        if ($webmDuration === null) {
            return 'ffprobe_failed';
        }

        if ($wavDuration === null) {
            return 'wav_duration_failed';
        }

        return null;
    }

    /**
     * @param  array<string, ?float>  $timestamps
     */
    private function timestampsAreOrdered(array $timestamps): bool
    {
        if ($timestamps['recording_started_ms'] !== 0.0) {
            return false;
        }

        $ordered = [
            $timestamps['recording_started_ms'],
            $timestamps['stop_requested_ms'],
            $timestamps['recorder_stop_called_ms'],
            $timestamps['last_dataavailable_ms'],
            $timestamps['stop_event_ms'],
            $timestamps['blob_completed_ms'],
        ];

        for ($index = 1; $index < count($ordered); $index++) {
            if ($ordered[$index] < $ordered[$index - 1]) {
                return false;
            }
        }

        return true;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function nullableFinitePositive(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) && $float > 0 ? $float : null;
    }

    /**
     * @param  resource  $handle
     */
    private function jsonlSize($handle): int
    {
        $statistics = fstat($handle);
        $size = $statistics['size'] ?? null;

        if (! is_int($size) || $size < 0) {
            throw new RuntimeException('storage_failed');
        }

        return $size;
    }

    /**
     * @param  resource  $handle
     */
    private function rollbackJsonl($handle, ?int $size): void
    {
        if ($size === null) {
            return;
        }

        $truncated = @ftruncate($handle, $size);
        $flushed = @fflush($handle);

        if (! $truncated || ! $flushed) {
            throw new RuntimeException('storage_failed');
        }
    }

    private function deleteRequestAudio(bool $mayDelete, string $audioPath): void
    {
        if (! $mayDelete || ! is_file($audioPath)) {
            return;
        }

        if (! @unlink($audioPath)) {
            throw new RuntimeException('storage_failed');
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $record
     */
    protected function appendJsonlRecord($handle, array $record): void
    {
        try {
            $line = json_encode(
                $record,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            )."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('storage_failed', previous: $exception);
        }

        fseek($handle, 0, SEEK_END);
        $length = strlen($line);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($handle, substr($line, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('storage_failed');
            }

            $written += $result;
        }

        if (! fflush($handle)) {
            throw new RuntimeException('storage_failed');
        }
    }
}
