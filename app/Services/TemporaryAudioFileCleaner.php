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
                'submission_id' => $submission->id,
                'submission_status' => $submission->status,
                'cleanup_context' => $context,
            ]);

            return self::SKIPPED;
        }

        $logContext = [
            'submission_id' => $submission->id,
            'submission_status' => $submission->status,
            'audio_path' => $submission->audio_path,
            'cleanup_context' => $context,
        ];

        try {
            $disk = Storage::disk('local');

            if (! $disk->exists($submission->audio_path)) {
                Log::info('Temporary audio file already missing.', $logContext);

                return self::MISSING;
            }

            $deleted = $disk->delete($submission->audio_path);
        } catch (Throwable $exception) {
            Log::warning('Failed to delete temporary audio file.', $logContext + [
                'exception' => $exception->getMessage(),
            ]);

            return self::FAILED;
        }

        if ($deleted === false) {
            Log::warning('Temporary audio file was not deleted.', $logContext);

            return self::FAILED;
        }

        Log::info('Temporary audio file deleted.', $logContext);

        return self::DELETED;
    }
}
