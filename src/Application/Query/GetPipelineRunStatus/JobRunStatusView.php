<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class JobRunStatusView
{
    public function __construct(
        public string $id,
        public string $jobId,
        public string $status,
    ) {}
}
