<?php

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Console\Kernel;
use Tests\Fixtures\Jobs\RecordFeatureFlagsJob;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php dispatch_worker_flag_probe.php <queue> <output> <label>\n");
    exit(1);
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$job = (new RecordFeatureFlagsJob($argv[2], $argv[3]))
    ->onConnection('database')
    ->onQueue($argv[1]);

$app->make(Dispatcher::class)->dispatch($job);
