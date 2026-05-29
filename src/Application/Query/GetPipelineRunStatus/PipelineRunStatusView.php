<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class PipelineRunStatusView
{
    /** @param JobRunStatusView[] $jobs */
    public function __construct(
        public string $id,
        public string $pipelineId,
        public string $status,
        public string $environment,
        public array $jobs,
    ) {}
}
