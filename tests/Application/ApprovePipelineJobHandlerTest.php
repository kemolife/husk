<?php

namespace App\Tests\Application;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobHandler;
use App\Application\Port\ApprovalRecordRepositoryPort;
use App\Application\Port\NotificationPort;
use App\Application\Port\PipelineEventRepositoryPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class ApprovePipelineJobHandlerTest extends TestCase
{
    public function test_approving_resumes_pipeline(): void
    {
        $runRepo = new InMemoryPipelineRunRepository();
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', [
            new Job('gate', JobType::APPROVAL, null, null, [], null),
            new Job('deploy', JobType::SCRIPT, 'alpine', 'echo deploy', ['gate'], null),
        ]);

        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $gateRun = $run->jobRunByJobId('gate');
        $gateRun->markAsRunning();
        $gateRun->markAsAwaitingApproval();
        $run->markAsAwaitingApproval();
        $runRepo->save($run);

        $pipelineRepo = $this->createConfiguredStub(PipelineRepositoryPort::class, ['findById' => $pipeline]);
        $handler = new ApprovePipelineJobHandler($pipelineRepo, $runRepo, new MessageBus([]), $this->createStub(ApprovalRecordRepositoryPort::class), $this->createStub(PipelineEventRepositoryPort::class), $this->createStub(NotificationPort::class));
        $handler(new ApprovePipelineJobCommand($run->id()->value, 'gate', true));

        $updated = $runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::RUNNING, $updated->status());
    }

    public function test_rejecting_marks_pipeline_failed(): void
    {
        $runRepo = new InMemoryPipelineRunRepository();
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', [
            new Job('gate', JobType::APPROVAL, null, null, [], null),
        ]);

        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $gateRun = $run->jobRunByJobId('gate');
        $gateRun->markAsRunning();
        $gateRun->markAsAwaitingApproval();
        $run->markAsAwaitingApproval();
        $runRepo->save($run);

        $pipelineRepo = $this->createConfiguredStub(PipelineRepositoryPort::class, ['findById' => $pipeline]);
        $handler = new ApprovePipelineJobHandler($pipelineRepo, $runRepo, new MessageBus([]), $this->createStub(ApprovalRecordRepositoryPort::class), $this->createStub(PipelineEventRepositoryPort::class), $this->createStub(NotificationPort::class));
        $handler(new ApprovePipelineJobCommand($run->id()->value, 'gate', false));

        $updated = $runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::FAILED, $updated->status());
    }
}
