<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class GetPipelineRunStatusQuery
{
    public function __construct(public string $pipelineRunId) {}
}
