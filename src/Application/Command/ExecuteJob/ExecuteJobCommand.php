<?php

namespace App\Application\Command\ExecuteJob;

final readonly class ExecuteJobCommand
{
    public function __construct(
        public string $pipelineRunId,
        public string $jobId,
        public int $attemptNumber = 1,
        public ?string $jobRunId = null,
    ) {}
}
