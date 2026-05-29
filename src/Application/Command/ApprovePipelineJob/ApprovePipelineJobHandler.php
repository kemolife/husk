<?php

namespace App\Application\Command\ApprovePipelineJob;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ApprovePipelineJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ApprovePipelineJobCommand $command): void
    {
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $run->jobRunByJobId($command->jobId);

        if (!$command->approved) {
            $jobRun->markAsFailed();
            $run->markAsFailed();
            $this->runRepo->save($run);
            return;
        }

        $jobRun->markAsSuccess();
        $run->markAsRunning();

        foreach ($run->readyJobs($pipeline) as $nextJobRun) {
            $nextJobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $nextJobRun->jobId()));
        }

        $this->runRepo->save($run);
    }
}
