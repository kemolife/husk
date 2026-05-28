<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;

interface PipelineRunRepositoryPort
{
    public function save(PipelineRun $run): void;
    public function findById(PipelineRunId $id): PipelineRun;
}
