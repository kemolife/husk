<?php

namespace App\Application\Command\RollbackPipeline;

final readonly class RollbackPipelineCommand
{
    public function __construct(
        public string $pipelineId,
        public string $environment,
        public string $newRunId,
    ) {}
}
