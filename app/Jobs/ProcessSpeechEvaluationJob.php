<?php

namespace App\Jobs;

use App\Dto\PythonEvaluationResult;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Services\PythonEvaluationClient;
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

    public function handle(PythonEvaluationClient $client): void
    {
        $submission = Submission::query()
            ->with('question:id,recommended_duration_seconds')
            ->find($this->submissionId);

        if (! $submission instanceof Submission || ! in_array($submission->status, ['pending', 'processing'], true)) {
            return;
        }

        if ($submission->status === 'pending') {
            $submission->forceFill([
                'status' => 'processing',
                'error_message' => null,
            ])->save();
        }

        $result = $client->evaluate(
            audioFilePath: Storage::disk('local')->path($submission->audio_path),
            submissionId: $submission->id,
            questionId: $submission->question_id,
            expectedDuration: (int) ($submission->question?->recommended_duration_seconds ?? 60),
            featureFlags: $this->featureFlags(),
        );

        if ($result->success) {
            $this->completeSubmission($submission, $result);

            return;
        }

        if ($result->retryable) {
            throw new RuntimeException($this->failureMessage($result));
        }

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

        $this->deleteTemporaryAudioFile($submission);
    }

    private function completeSubmission(Submission $submission, PythonEvaluationResult $result): void
    {
        Evaluation::query()->updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'transcript' => $result->transcript,
                'duration_seconds' => $result->recognizedDurationSeconds ?? $result->audioDurationSeconds,
                'characters_per_minute' => $this->charactersPerMinute($result),
                'speed_assessment' => $this->speedAssessment($result),
                'azure_request_id' => $result->azureRequestId,
                'raw_azure_response' => $result->rawAzureResponse,
            ],
        );

        $submission->forceFill([
            'status' => 'completed',
            'error_message' => null,
            'completed_at' => now(),
        ])->save();

        $this->deleteTemporaryAudioFile($submission);
    }

    private function failSubmission(Submission $submission, PythonEvaluationResult $result): void
    {
        $submission->forceFill([
            'status' => 'failed',
            'error_message' => $this->failureMessage($result),
            'completed_at' => now(),
        ])->save();

        $this->deleteTemporaryAudioFile($submission);
    }

    private function deleteTemporaryAudioFile(Submission $submission): void
    {
        if (! is_string($submission->audio_path) || $submission->audio_path === '') {
            return;
        }

        try {
            $deleted = Storage::disk('local')->delete($submission->audio_path);
        } catch (Throwable $exception) {
            Log::warning('Failed to delete temporary audio file.', [
                'submission_id' => $submission->id,
                'audio_path' => $submission->audio_path,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if ($deleted === false) {
            Log::warning('Temporary audio file was not deleted.', [
                'submission_id' => $submission->id,
                'audio_path' => $submission->audio_path,
            ]);
        }
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
