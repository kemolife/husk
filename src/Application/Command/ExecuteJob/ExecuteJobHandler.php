<?php

namespace App\Application\Command\ExecuteJob;

use App\Application\Port\ExecutorPort;
use App\Application\Port\NotificationPort;
use App\Application\Port\PipelineEventRepositoryPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineEvent;
use App\Domain\PipelineRun\PipelineEventType;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class ExecuteJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly ExecutorPort $executor,
        private readonly MessageBusInterface $bus,
        private readonly PipelineEventRepositoryPort $eventRepo,
        private readonly NotificationPort $notifier,
    ) {}

    public function __invoke(ExecuteJobCommand $command): void
    {
        // Phase 1: read initial state (no lock) — needed to get matrix values and check approval
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $command->jobRunId !== null
            ? $run->jobRunById($command->jobRunId)
            : $run->jobRunByJobId($command->jobId);

        $job = $pipeline->job($command->jobId)->withMatrixValues($jobRun->matrixValues() ?? []);

        // Approval gate: mark and return (single-path, no concurrency concern)
        if ($job->isApproval()) {
            $jobRun->markAsAwaitingApproval();
            $run->markAsAwaitingApproval();
            $this->runRepo->save($run);

            $this->eventRepo->record(new PipelineEvent(
                Uuid::v4()->toRfc4122(),
                $command->pipelineRunId,
                PipelineEventType::APPROVAL_REQUESTED,
                ['jobId' => $command->jobId],
            ));
            return;
        }

        $this->eventRepo->record(new PipelineEvent(
            Uuid::v4()->toRfc4122(),
            $command->pipelineRunId,
            PipelineEventType::JOB_STARTED,
            ['jobId' => $command->jobId, 'attempt' => $command->attemptNumber],
        ));

        // Phase 2: execute job in Docker (long-running, outside any lock)
        $startedAt = new \DateTimeImmutable();
        $result = $this->executor->run($job, $run->environment(), $command->pipelineRunId);
        $finishedAt = new \DateTimeImmutable();

        // Retry before lock — we haven't committed a terminal state yet
        if (!$result->isSuccess()) {
            $retryPolicy = $job->retry;
            if ($retryPolicy !== null && $command->attemptNumber < $retryPolicy->maxAttempts) {
                if ($retryPolicy->delaySeconds > 0) {
                    sleep($retryPolicy->delaySeconds);
                }
                $this->bus->dispatch(new ExecuteJobCommand(
                    $command->pipelineRunId,
                    $command->jobId,
                    $command->attemptNumber + 1,
                    $command->jobRunId,
                ));
                return;
            }
        }

        // Phase 3: commit result + dispatch next jobs — locked so concurrent workers on the
        // same pipeline run serialize here and each sees the other's completed jobs.
        $runId = new PipelineRunId($command->pipelineRunId);
        $runCompleted = $this->runRepo->withLock($runId, function (PipelineRun $freshRun) use ($command, $result, $pipeline, $job, $startedAt, $finishedAt) {
            $freshJobRun = $freshRun->jobRunById($command->jobRunId);
            $freshJobRun->recordExecution($result->output, $startedAt, $finishedAt);

            if ($result->isSuccess()) {
                $freshJobRun->markAsSuccess();

                $this->eventRepo->record(new PipelineEvent(
                    Uuid::v4()->toRfc4122(),
                    $command->pipelineRunId,
                    PipelineEventType::JOB_COMPLETED,
                    ['jobId' => $command->jobId, 'status' => 'success'],
                ));
            } else {
                $freshJobRun->markAsFailed();

                $this->eventRepo->record(new PipelineEvent(
                    Uuid::v4()->toRfc4122(),
                    $command->pipelineRunId,
                    PipelineEventType::JOB_COMPLETED,
                    ['jobId' => $command->jobId, 'status' => 'failed'],
                ));

                if (!$job->continueOnError) {
                    $freshRun->markAsFailed();
                    return true;
                }
            }

            if ($freshRun->isComplete()) {
                $freshRun->markAsSuccess();
                return true;
            }

            foreach ($freshRun->readyJobs($pipeline) as $nextJobRun) {
                $nextJobRun->markAsRunning();
                $this->bus->dispatch(new ExecuteJobCommand(
                    $command->pipelineRunId,
                    $nextJobRun->jobId(),
                    1,
                    $nextJobRun->id(),
                ));
            }

            return false;
        });

        if ($runCompleted) {
            // Re-read status for notification (lock already released)
            $finalRun = $this->runRepo->findById($runId);
            $this->emitRunCompleted($command->pipelineRunId, $finalRun->pipelineId(), $finalRun->status()->value, $finalRun->environment()->value, $pipeline);
        }
    }

    private function emitRunCompleted(string $runId, string $pipelineId, string $status, string $environment, Pipeline $pipeline): void
    {
        $this->eventRepo->record(new PipelineEvent(
            Uuid::v4()->toRfc4122(),
            $runId,
            PipelineEventType::RUN_COMPLETED,
            ['status' => $status],
        ));

        $config = $pipeline->notifications();
        if ($config !== null) {
            try {
                $this->notifier->notify('run_completed', $runId, $pipelineId, $status, $environment, $config);
            } catch (\Throwable) {
            }
        }
    }
}
