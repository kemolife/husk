<?php

namespace App\Application\Query\GetPipelineRunStatus;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetPipelineRunStatusHandler
{
    public function __construct(private readonly PipelineRunRepositoryPort $runRepo) {}

    public function __invoke(GetPipelineRunStatusQuery $query): PipelineRunStatusView
    {
        $run = $this->runRepo->findById(new PipelineRunId($query->pipelineRunId));

        $jobs = array_map(
            fn($jr) => new JobRunStatusView($jr->id(), $jr->jobId(), $jr->status()->value),
            $run->jobRuns()
        );

        return new PipelineRunStatusView(
            $run->id()->value,
            $run->pipelineId(),
            $run->status()->value,
            $run->environment()->value,
            $jobs,
        );
    }
}
