<?php

namespace App\Application\Command\ApprovePipelineJob;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Port\ApprovalRecordRepositoryPort;
use App\Application\Port\NotificationPort;
use App\Application\Port\PipelineEventRepositoryPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\ApprovalRecord;
use App\Domain\PipelineRun\PipelineEvent;
use App\Domain\PipelineRun\PipelineEventType;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class ApprovePipelineJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
        private readonly ApprovalRecordRepositoryPort $approvalRepo,
        private readonly PipelineEventRepositoryPort $eventRepo,
        private readonly NotificationPort $notifier,
    ) {}

    public function __invoke(ApprovePipelineJobCommand $command): void
    {
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $run->jobRunByJobId($command->jobId);

        $this->approvalRepo->save(new ApprovalRecord(
            Uuid::v4()->toRfc4122(),
            $command->pipelineRunId,
            $jobRun->id(),
            $command->actorId,
            $command->approved,
        ));

        if (!$command->approved) {
            $jobRun->markAsFailed();
            $run->markAsFailed();
            $this->runRepo->save($run);

            $this->eventRepo->record(new PipelineEvent(
                Uuid::v4()->toRfc4122(),
                $command->pipelineRunId,
                PipelineEventType::REJECTED,
                ['jobId' => $command->jobId, 'actor' => $command->actorId],
            ));
            $this->eventRepo->record(new PipelineEvent(
                Uuid::v4()->toRfc4122(),
                $command->pipelineRunId,
                PipelineEventType::RUN_COMPLETED,
                ['status' => 'failed'],
            ));

            $config = $pipeline->notifications();
            if ($config !== null) {
                try { $this->notifier->notify('run_completed', $run->id()->value, $run->pipelineId(), 'failed', $run->environment()->value, $config); } catch (\Throwable) {}
            }
            return;
        }

        $jobRun->markAsSuccess();
        $run->markAsRunning();

        $this->eventRepo->record(new PipelineEvent(
            Uuid::v4()->toRfc4122(),
            $command->pipelineRunId,
            PipelineEventType::APPROVED,
            ['jobId' => $command->jobId, 'actor' => $command->actorId],
        ));

        foreach ($run->readyJobs($pipeline) as $nextJobRun) {
            $nextJobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $nextJobRun->jobId(), 1, $nextJobRun->id()));
        }

        $this->runRepo->save($run);
    }
}
