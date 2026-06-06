<?php

namespace App\Application\Command\TriggerPipeline;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Port\PipelineEventRepositoryPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineEvent;
use App\Domain\PipelineRun\PipelineEventType;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\Shared\Environment;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class TriggerPipelineHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
        private readonly PipelineEventRepositoryPort $eventRepo,
    ) {}

    public function __invoke(TriggerPipelineCommand $command): void
    {
        $pipeline = $this->pipelineRepo->findById(new PipelineId($command->pipelineId));
        $environment = Environment::fromString($command->environment);

        $run = new PipelineRun(new PipelineRunId($command->pipelineRunId), $pipeline, $environment);
        $run->markAsRunning();

        $this->runRepo->save($run);

        $this->eventRepo->record(new PipelineEvent(
            Uuid::v4()->toRfc4122(),
            $command->pipelineRunId,
            PipelineEventType::TRIGGERED,
            ['pipelineId' => $command->pipelineId, 'environment' => $command->environment],
        ));

        foreach ($run->readyJobs($pipeline) as $jobRun) {
            $jobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $jobRun->jobId(), 1, $jobRun->id()));
        }

        $this->runRepo->save($run);
    }
}
