<?php

namespace App\Tests\Application;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Application\Command\TriggerPipeline\TriggerPipelineHandler;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class TriggerPipelineHandlerTest extends TestCase
{
    private InMemoryPipelineRunRepository $runRepo;

    protected function setUp(): void
    {
        $this->runRepo = new InMemoryPipelineRunRepository();
    }

    private function makePipeline(array $jobs): Pipeline
    {
        return new Pipeline(new PipelineId('p1'), 'P1', $jobs);
    }

    public function test_it_creates_pipeline_run_and_dispatches_ready_jobs(): void
    {
        $pipeline = $this->makePipeline([
            new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null),
            new Job('test', JobType::SCRIPT, 'php:8.4-cli', 'phpunit', ['build'], null),
        ]);

        $pipelineRepo = $this->createConfiguredStub(PipelineRepositoryPort::class, [
            'findById' => $pipeline,
        ]);

        $runId = PipelineRunId::generate();
        $bus = new MessageBus([]);
        $handler = new TriggerPipelineHandler($pipelineRepo, $this->runRepo, $bus);
        $handler(new TriggerPipelineCommand($runId->value, 'p1', 'staging'));

        $run = $this->runRepo->findById($runId);
        $this->assertSame(PipelineRunStatus::RUNNING, $run->status());
    }
}
