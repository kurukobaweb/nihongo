<?php

namespace Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class RecordFeatureFlagsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $outputPath,
        public readonly string $label,
    ) {}

    public function handle(): void
    {
        $record = json_encode([
            'label' => $this->label,
            'feature_flags' => [
                'pronunciation_assessment' => (bool) config('features.speech_pronunciation_assessment_enabled'),
                'fluency_assessment' => (bool) config('features.speech_fluency_assessment_enabled'),
            ],
        ], JSON_THROW_ON_ERROR);

        if (file_put_contents($this->outputPath, $record.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the worker Feature Flag probe result.');
        }
    }
}
