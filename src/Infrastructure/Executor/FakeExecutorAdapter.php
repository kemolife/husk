<?php

namespace App\Infrastructure\Executor;

use App\Application\Port\ExecutorPort;
use App\Application\Port\JobResult;
use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

class FakeExecutorAdapter implements ExecutorPort
{
    public function run(Job $job, Environment $environment, string $runId): JobResult
    {
        return JobResult::success(
            sprintf('Simulated: would run "%s" on image "%s" in %s (run: %s)', $job->script, $job->image, $environment->value, $runId)
        );
    }
}
