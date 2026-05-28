<?php

namespace App\Application\Port;

use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;

interface PipelineRepositoryPort
{
    public function findById(PipelineId $id): Pipeline;
}
