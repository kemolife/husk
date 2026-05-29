<?php

namespace App\Application\Command\TriggerPipeline;

final readonly class TriggerPipelineCommand
{
    public function __construct(
        public string $pipelineRunId,
        public string $pipelineId,
        public string $environment,
    ) {}
}
