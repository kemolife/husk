<?php

namespace App\Application\Command\RollbackPipeline;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use App\Domain\Shared\Environment;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class RollbackPipelineHandler
{
    public function __construct(
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(RollbackPipelineCommand $command): void
    {
        $env = Environment::fromString($command->environment);
        $lastSuccess = $this->runRepo->findLastSuccess($command->pipelineId, $env);

        if ($lastSuccess === null) {
            throw new PipelineRunNotFoundException("No successful run found for pipeline '{$command->pipelineId}' in environment '{$command->environment}'");
        }

        $this->bus->dispatch(new TriggerPipelineCommand(
            pipelineRunId: $command->newRunId,
            pipelineId: $command->pipelineId,
            environment: $command->environment,
        ));
    }
}
