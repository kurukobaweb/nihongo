<?php

namespace App\Jobs;

use App\Contracts\CommentGeneratorInterface;
use App\Dto\EvaluationResult;
use App\Dto\PythonEvaluationResult;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Services\PythonEvaluationClient;
use App\Services\TemporaryAudioFileCleaner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessSpeechEvaluationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $submissionId,
    ) {}

    public function handle(PythonEvaluationClient $client, CommentGeneratorInterface $commentGenerator): void
    {
        $submission = Submission::query()
            ->with('question:id,recommended_duration_seconds')
            ->find($this->submissionId);

        if (! $submission instanceof Submission || ! in_array($submission->status, ['pending', 'processing'], true)) {
            return;
        }

        Log::info('Speech evaluation job started.', $this->logContext($submission, 'evaluation_job_started'));

        if ($submission->status === 'pending') {
            $submission->forceFill([
                'status' => 'processing',
                'error_message' => null,
            ])->save();
        }

        $featureFlags = $this->featureFlags();

        Log::info('Speech evaluation request prepared.', $this->logContext(
            $submission,
            'evaluation_request_prepared',
            ['status' => $submission->status],
        ));

        $result = $client->evaluate(
            audioFilePath: Storage::disk('local')->path($submission->audio_path),
            submissionId: $submission->id,
            questionId: $submission->question_id,
            expectedDuration: (int) ($submission->question?->recommended_duration_seconds ?? 60),
            featureFlags: $featureFlags,
        );

        if ($result->success) {
            Log::info('Speech evaluation succeeded.', $this->logContext(
                $submission,
                'evaluation_succeeded',
                ['http_status_code' => $result->httpStatus],
            ));

            $this->completeSubmission($submission, $result, $commentGenerator);

            return;
        }

        if ($result->retryable) {
            Log::warning('Speech evaluation failed with retryable error.', $this->failureLogContext(
                $submission,
                $result,
                'evaluation_failed_retryable',
            ));

            throw new RuntimeException($this->failureMessage($result));
        }

        Log::warning('Speech evaluation failed with non-retryable error.', $this->failureLogContext(
            $submission,
            $result,
            $result->httpStatus === 422 ? '422_non_retryable_occurred' : 'evaluation_failed_non_retryable',
        ));

        $this->failSubmission($submission, $result);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function failed(Throwable $exception): void
    {
        $submission = Submission::query()->find($this->submissionId);

        if (! $submission instanceof Submission || in_array($submission->status, ['completed', 'failed'], true)) {
            return;
        }

        $submission->forceFill([
            'status' => 'failed',
            'error_message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'python_evaluation_failed',
            'completed_at' => now(),
        ])->save();

        Log::warning('Speech evaluation submission marked failed after retries.', $this->logContext(
            $submission,
            'submission_marked_failed',
            [
                'status' => 'failed',
                'retryable' => true,
                'exception_class' => $exception::class,
            ],
        ));

        $this->deleteTemporaryAudioFile($submission);
    }

    private function completeSubmission(
        Submission $submission,
        PythonEvaluationResult $result,
        CommentGeneratorInterface $commentGenerator,
    ): void
    {
        $durationSeconds = $result->recognizedDurationSeconds ?? $result->audioDurationSeconds;
        $charactersPerMinute = $this->charactersPerMinute($result);
        $speedAssessment = $this->speedAssessment($result);
        $commentResult = $commentGenerator->generate(new EvaluationResult(
            transcript: $result->transcript,
            durationSeconds: $durationSeconds,
            charactersPerMinute: $charactersPerMinute,
            speedAssessment: $speedAssessment,
            metadata: [
                'submission_id' => $submission->id,
                'question_id' => $submission->question_id,
            ],
        ));

        $evaluation = Evaluation::query()->updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'transcript' => $result->transcript,
                'duration_seconds' => $durationSeconds,
                'characters_per_minute' => $charactersPerMinute,
                'speed_assessment' => $speedAssessment,
                'comment' => $commentResult->comment,
                'azure_request_id' => $result->azureRequestId,
                'raw_azure_response' => $result->rawAzureResponse,
            ],
        );

        Log::info('Speech evaluation saved.', $this->logContext(
            $submission,
            'evaluation_saved',
            ['evaluation_id' => $evaluation->id],
        ));

        $submission->forceFill([
            'status' => 'completed',
            'error_message' => null,
            'completed_at' => now(),
        ])->save();

        Log::info('Speech evaluation submission marked completed.', $this->logContext(
            $submission,
            'submission_marked_completed',
            [
                'status' => 'completed',
                'evaluation_id' => $evaluation->id,
            ],
        ));

        $this->deleteTemporaryAudioFile($submission);
    }

    private function failSubmission(Submission $submission, PythonEvaluationResult $result): void
    {
        $submission->forceFill([
            'status' => 'failed',
            'error_message' => $this->failureMessage($result),
            'completed_at' => now(),
        ])->save();

        Log::warning('Speech evaluation submission marked failed.', $this->failureLogContext(
            $submission,
            $result,
            'submission_marked_failed',
            ['status' => 'failed'],
        ));

        $this->deleteTemporaryAudioFile($submission);
    }

    private function deleteTemporaryAudioFile(Submission $submission): void
    {
        $deleteResult = app(TemporaryAudioFileCleaner::class)
            ->deleteForSubmission($submission, 'process_speech_evaluation_job');

        $level = $deleteResult === TemporaryAudioFileCleaner::FAILED ? 'warning' : 'info';
        $event = match ($deleteResult) {
            TemporaryAudioFileCleaner::DELETED => 'temporary_audio_delete_succeeded',
            TemporaryAudioFileCleaner::MISSING => 'temporary_audio_delete_missing',
            TemporaryAudioFileCleaner::SKIPPED => 'temporary_audio_delete_skipped',
            default => 'temporary_audio_delete_failed',
        };

        Log::{$level}('Speech evaluation temporary audio cleanup finished.', $this->logContext(
            $submission,
            $event,
            [
                'status' => $submission->status,
                'delete_result' => $deleteResult,
                'audio_file' => $this->safeAudioFileName($submission),
            ],
        ));
    }

    private function failureMessage(PythonEvaluationResult $result): string
    {
        $parts = array_filter([
            $result->errorType,
            $result->detail,
        ]);

        return $parts === []
            ? 'python_evaluation_failed'
            : implode(': ', $parts);
    }

    private function charactersPerMinute(PythonEvaluationResult $result): int|null
    {
        $value = $result->speechRate['characters_per_minute'] ?? null;

        return is_int($value) || is_float($value) ? (int) round($value) : null;
    }

    private function speedAssessment(PythonEvaluationResult $result): string|null
    {
        $value = $result->speechRate['assessment'] ?? null;

        return is_string($value) && in_array($value, ['slow', 'appropriate', 'fast'], true)
            ? $value
            : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function logContext(Submission $submission, string $event, array $extra = []): array
    {
        return array_filter([
            'event' => $event,
            'submission_id' => $submission->id,
            'question_id' => $submission->question_id,
            'job_class' => self::class,
            'status' => $extra['status'] ?? $submission->status,
            'attempt_count' => $this->attempts(),
            'evaluation_id' => $extra['evaluation_id'] ?? null,
            'retryable' => $extra['retryable'] ?? null,
            'error_category' => $extra['error_category'] ?? null,
            'exception_class' => $extra['exception_class'] ?? null,
            'http_status_code' => $extra['http_status_code'] ?? null,
            'delete_result' => $extra['delete_result'] ?? null,
            'audio_file' => $extra['audio_file'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function failureLogContext(
        Submission $submission,
        PythonEvaluationResult $result,
        string $event,
        array $extra = [],
    ): array {
        return $this->logContext($submission, $event, $extra + [
            'retryable' => $result->retryable,
            'error_category' => $result->errorType,
            'http_status_code' => $result->httpStatus,
        ]);
    }

    private function safeAudioFileName(Submission $submission): string|null
    {
        return is_string($submission->audio_path) && $submission->audio_path !== ''
            ? basename(str_replace('\\', '/', $submission->audio_path))
            : null;
    }

    /**
     * @return array<string, bool|array<string, bool>>
     */
    private function featureFlags(): array
    {
        return [
            'pronunciation_assessment' => (bool) config('features.speech_pronunciation_assessment_enabled', false),
            'fluency_assessment' => (bool) config('features.speech_fluency_assessment_enabled', false),
            'content_assessment' => (bool) config('features.speech_content_assessment_enabled', false),
            'comment' => [
                'llm_generation' => (bool) config('features.comment_llm_generation_enabled', false),
            ],
        ];
    }
}
