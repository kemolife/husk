<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\PipelineEvent;

interface PipelineEventRepositoryPort
{
    public function record(PipelineEvent $event): void;

    /** @return PipelineEvent[] */
    public function findByRunId(string $runId): array;
}
