<?php

namespace App\Jobs;

use App\Models\Submission;
use App\Services\TemporaryAudioFileCleaner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupTempFilesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(TemporaryAudioFileCleaner $cleaner): void
    {
        Submission::query()
            ->whereIn('status', ['completed', 'failed'])
            ->whereNotNull('audio_path')
            ->orderBy('id')
            ->chunk(100, function ($submissions) use ($cleaner): void {
                foreach ($submissions as $submission) {
                    if ($submission instanceof Submission) {
                        $cleaner->deleteForSubmission($submission, 'cleanup_temp_files_job');
                    }
                }
            });
    }
}
