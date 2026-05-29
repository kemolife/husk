<?php

namespace App\Application\Command\ApprovePipelineJob;

final readonly class ApprovePipelineJobCommand
{
    public function __construct(
        public string $pipelineRunId,
        public string $jobId,
        public bool $approved,
    ) {}
}
