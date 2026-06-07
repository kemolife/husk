<?php

namespace App\Application\Command\TriggerPipeline;

final readonly class TriggerPipelineCommand
{
    public function __construct(
        public string $pipelineRunId,
        public string $pipelineId,
        public string $environment,
        public ?string $branch = null,
        public ?string $commitSha = null,
        public ?string $actor = null,
    ) {}
}
