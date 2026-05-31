<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;

interface PipelineRunRepositoryPort
{
    public function save(PipelineRun $run): void;

    /** @throws PipelineRunNotFoundException */
    public function findById(PipelineRunId $id): PipelineRun;

    /** @return PipelineRun[] */
    public function findByPipelineId(string $pipelineId, int $limit = 20): array;
}
