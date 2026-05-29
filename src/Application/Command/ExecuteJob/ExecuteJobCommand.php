<?php

namespace App\Application\Command\ExecuteJob;

final readonly class ExecuteJobCommand
{
    public function __construct(
        public string $pipelineRunId,
        public string $jobId,
    ) {}
}
