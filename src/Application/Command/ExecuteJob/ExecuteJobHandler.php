<?php

namespace App\Application\Command\ExecuteJob;

use App\Application\Port\ExecutorPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ExecuteJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly ExecutorPort $executor,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ExecuteJobCommand $command): void
    {
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $run->jobRunByJobId($command->jobId);
        $job = $pipeline->job($command->jobId);

        if ($job->isApproval()) {
            $jobRun->markAsAwaitingApproval();
            $run->markAsAwaitingApproval();
            $this->runRepo->save($run);
            return;
        }

        $startedAt = new \DateTimeImmutable();
        $result = $this->executor->run($job, $run->environment(), $command->pipelineRunId);
        $finishedAt = new \DateTimeImmutable();
        $jobRun->recordExecution($result->output, $startedAt, $finishedAt);

        if ($result->isSuccess()) {
            $jobRun->markAsSuccess();
        } else {
            $jobRun->markAsFailed();
            $run->markAsFailed();
            $this->runRepo->save($run);
            return;
        }

        if ($run->isComplete()) {
            $run->markAsSuccess();
            $this->runRepo->save($run);
            return;
        }

        foreach ($run->readyJobs($pipeline) as $nextJobRun) {
            $nextJobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $nextJobRun->jobId()));
        }

        $this->runRepo->save($run);
    }
}
