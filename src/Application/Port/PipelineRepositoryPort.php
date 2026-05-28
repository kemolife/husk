<?php

namespace App\Application\Port;

use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;

interface PipelineRepositoryPort
{
    /** @throws PipelineNotFoundException */
    public function findById(PipelineId $id): Pipeline;
}
