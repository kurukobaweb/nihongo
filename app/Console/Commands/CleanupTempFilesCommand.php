<?php

namespace App\Console\Commands;

use App\Jobs\CleanupTempFilesJob;
use Illuminate\Console\Command;

class CleanupTempFilesCommand extends Command
{
    protected $signature = 'audio:cleanup-temp-files';

    protected $description = 'Clean up residual temporary audio files after submissions are completed or failed.';

    public function handle(): int
    {
        app()->call([new CleanupTempFilesJob(), 'handle']);

        $this->info('Temporary audio cleanup finished.');

        return self::SUCCESS;
    }
}
