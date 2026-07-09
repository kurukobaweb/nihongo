<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TemporaryAudioFileCleaner
{
    public const DELETED = 'deleted';

    public const MISSING = 'missing';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public function deleteForSubmission(Submission $submission, string $context): string
    {
        if (! is_string($submission->audio_path) || $submission->audio_path === '') {
            Log::info('Temporary audio file cleanup skipped because path is empty.', [
                'event' => 'temporary_audio_delete_skipped',
                'submission_id' => $submission->id,
                'submission_status' => $submission->status,
                'cleanup_context' => $context,
                'delete_result' => self::SKIPPED,
            ]);

            return self::SKIPPED;
        }

        $logContext = [
            'submission_id' => $submission->id,
            'submission_status' => $submission->status,
            'audio_file' => $this->safeAudioFileName($submission->audio_path),
            'cleanup_context' => $context,
        ];

        try {
            $disk = Storage::disk('local');

            if (! $disk->exists($submission->audio_path)) {
                Log::info('Temporary audio file already missing.', $logContext + [
                    'event' => 'temporary_audio_delete_missing',
                    'delete_result' => self::MISSING,
                ]);

                return self::MISSING;
            }

            $deleted = $disk->delete($submission->audio_path);
        } catch (Throwable $exception) {
            Log::warning('Failed to delete temporary audio file.', $logContext + [
                'event' => 'temporary_audio_delete_failed',
                'delete_result' => self::FAILED,
                'exception_class' => $exception::class,
            ]);

            return self::FAILED;
        }

        if ($deleted === false) {
            Log::warning('Temporary audio file was not deleted.', $logContext + [
                'event' => 'temporary_audio_delete_failed',
                'delete_result' => self::FAILED,
            ]);

            return self::FAILED;
        }

        Log::info('Temporary audio file deleted.', $logContext + [
            'event' => 'temporary_audio_delete_succeeded',
            'delete_result' => self::DELETED,
        ]);

        return self::DELETED;
    }

    private function safeAudioFileName(string $audioPath): string
    {
        return basename(str_replace('\\', '/', $audioPath));
    }
}
