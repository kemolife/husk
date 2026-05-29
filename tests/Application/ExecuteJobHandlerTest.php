<?php

namespace App\Tests\Application;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Command\ExecuteJob\ExecuteJobHandler;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Infrastructure\Executor\FakeExecutorAdapter;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class ExecuteJobHandlerTest extends TestCase
{
    private InMemoryPipelineRunRepository $runRepo;
    private FakeExecutorAdapter $executor;
    private MessageBus $bus;

    protected function setUp(): void
    {
        $this->runRepo = new InMemoryPipelineRunRepository();
        $this->executor = new FakeExecutorAdapter();
        $this->bus = new MessageBus([]);
    }

    private function createRun(array $jobs, Environment $env = Environment::STAGING): array
    {
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', $jobs);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, $env);
        $run->markAsRunning();
        $this->runRepo->save($run);
        return [$pipeline, $run];
    }

    public function test_successful_job_marks_run_as_success_when_complete(): void
    {
        [$pipeline, $run] = $this->createRun([
            new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null),
        ]);

        $jobRun = $run->jobRunByJobId('build');
        $jobRun->markAsRunning();
        $this->runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);

        $handler = new ExecuteJobHandler($pipelineRepo, $this->runRepo, $this->executor, $this->bus);
        $handler(new ExecuteJobCommand($run->id()->value, 'build'));

        $updated = $this->runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::SUCCESS, $updated->status());
    }

    public function test_approval_job_marks_pipeline_as_awaiting_approval(): void
    {
        [$pipeline, $run] = $this->createRun([
            new Job('gate', JobType::APPROVAL, null, null, [], null),
        ]);

        $jobRun = $run->jobRunByJobId('gate');
        $jobRun->markAsRunning();
        $this->runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);

        $handler = new ExecuteJobHandler($pipelineRepo, $this->runRepo, $this->executor, $this->bus);
        $handler(new ExecuteJobCommand($run->id()->value, 'gate'));

        $updated = $this->runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::AWAITING_APPROVAL, $updated->status());
    }
}
