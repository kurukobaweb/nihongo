<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QueueWorkerFeatureFlagReloadTest extends TestCase
{
    public function test_real_worker_process_reads_updated_feature_flags_after_restart(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The worker process verification requires pdo_sqlite.');
        }

        $temporaryDirectory = storage_path('framework/testing/t01205-'.bin2hex(random_bytes(6)));
        $databasePath = $temporaryDirectory.'/queue.sqlite';
        $outputPath = $temporaryDirectory.'/worker-flags.jsonl';
        $queue = 't01205-'.bin2hex(random_bytes(4));
        $worker = null;

        mkdir($temporaryDirectory, 0777, true);
        touch($databasePath);
        $this->createJobsTable($databasePath);

        $flagsOff = $this->processEnvironment($databasePath, false);
        $flagsOn = $this->processEnvironment($databasePath, true);

        try {
            $this->runArtisan(['config:clear'], $flagsOff);
            $this->runArtisan(['config:cache'], $flagsOff);

            $worker = $this->startWorker($queue, $flagsOff, maxJobs: 2);
            $this->dispatchProbe($queue, $outputPath, 'before_cache_change', $flagsOff);
            $this->waitForRecord($outputPath, 'before_cache_change', $worker);

            $this->runArtisan(['config:clear'], $flagsOn);
            $this->runArtisan(['config:cache'], $flagsOn);

            $this->dispatchProbe($queue, $outputPath, 'before_worker_restart', $flagsOn);
            $this->waitForRecord($outputPath, 'before_worker_restart', $worker);

            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $worker = $this->startWorker($queue, $flagsOn, maxJobs: 1);

            $this->dispatchProbe($queue, $outputPath, 'after_worker_restart', $flagsOn);
            $this->waitForRecord($outputPath, 'after_worker_restart', $worker);
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

            $records = $this->recordsByLabel($outputPath);

            $this->assertSame($this->expectedFlags(false), $records['before_cache_change']['feature_flags']);
            $this->assertSame($this->expectedFlags(false), $records['before_worker_restart']['feature_flags']);
            $this->assertSame($this->expectedFlags(true), $records['after_worker_restart']['feature_flags']);
        } finally {
            if ($worker instanceof Process && $worker->isRunning()) {
                $worker->stop(3);
            }

            $this->runArtisan(['config:clear'], $flagsOff, failOnError: false);
            (new Filesystem)->deleteDirectory($temporaryDirectory);
        }
    }

    /**
     * @return array<string, string>
     */
    private function processEnvironment(string $databasePath, bool $enabled): array
    {
        $flag = $enabled ? 'true' : 'false';

        return [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $databasePath,
            'QUEUE_CONNECTION' => 'database',
            'SESSION_DRIVER' => 'array',
            'SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED' => $flag,
            'SPEECH_FLUENCY_ASSESSMENT_ENABLED' => $flag,
        ];
    }

    private function createJobsTable(string $databasePath): void
    {
        $pdo = new PDO('sqlite:'.$databasePath);
        $pdo->exec(
            'CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX jobs_queue_index ON jobs (queue)');
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function startWorker(string $queue, array $environment, int $maxJobs): Process
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'queue:work',
            'database',
            '--queue='.$queue,
            '--sleep=1',
            '--tries=1',
            '--timeout=10',
            '--max-jobs='.$maxJobs,
            '--max-time=15',
            '--no-interaction',
        ], base_path(), $environment);
        $process->setTimeout(30);
        $process->start();

        usleep(300_000);
        $this->assertTrue($process->isRunning(), $process->getErrorOutput());
        $this->assertNotNull($process->getPid());

        return $process;
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function dispatchProbe(string $queue, string $outputPath, string $label, array $environment): void
    {
        $process = new Process([
            PHP_BINARY,
            'tests/Support/dispatch_worker_flag_probe.php',
            $queue,
            $outputPath,
            $label,
        ], base_path(), $environment);
        $process->setTimeout(15);
        $process->mustRun();
    }

    /**
     * @param  array<string, string>  $environment
     */
    private function runArtisan(array $arguments, array $environment, bool $failOnError = true): void
    {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], base_path(), $environment);
        $process->setTimeout(30);
        $process->run();

        if ($failOnError) {
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        }
    }

    private function waitForRecord(string $outputPath, string $label, Process $worker): void
    {
        $deadline = microtime(true) + 10;

        do {
            if (isset($this->recordsByLabel($outputPath)[$label])) {
                return;
            }

            if (! $worker->isRunning()) {
                $this->fail('Queue worker stopped unexpectedly: '.$worker->getErrorOutput());
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        $this->fail("Timed out waiting for worker probe record [{$label}].");
    }

    /**
     * @return array<string, array{label: string, feature_flags: array<string, bool>}>
     */
    private function recordsByLabel(string $outputPath): array
    {
        if (! is_file($outputPath)) {
            return [];
        }

        $records = [];

        foreach (file($outputPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            $records[$record['label']] = $record;
        }

        return $records;
    }

    /**
     * @return array<string, bool>
     */
    private function expectedFlags(bool $enabled): array
    {
        return [
            'pronunciation_assessment' => $enabled,
            'fluency_assessment' => $enabled,
        ];
    }
}
