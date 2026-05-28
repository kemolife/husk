<?php

namespace App\Tests\Domain\PipelineRun;

use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\JobRunStatus;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Domain\Shared\InvalidStatusTransitionException;
use PHPUnit\Framework\TestCase;

class PipelineRunTest extends TestCase
{
    private function makePipeline(array $jobs): Pipeline
    {
        return new Pipeline(new PipelineId('p1'), 'P1', $jobs);
    }

    private function makeJob(string $id, array $needs = [], ?string $condition = null, JobType $type = JobType::SCRIPT): Job
    {
        return new Job($id, $type, 'php:8.4-cli', 'echo done', $needs, $condition);
    }

    public function test_starts_as_pending(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $this->assertSame(PipelineRunStatus::PENDING, $run->status());
    }

    public function test_ready_jobs_returns_jobs_with_no_needs(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('lint'),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(2, $ready);
    }

    public function test_ready_jobs_respects_needs(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(1, $ready);
        $this->assertSame('build', $ready[0]->jobId());
    }

    public function test_ready_jobs_unlocks_after_dependency_succeeds(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();

        $buildRun = $run->jobRunByJobId('build');
        $buildRun->markAsRunning();
        $buildRun->markAsSuccess();

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(1, $ready);
        $this->assertSame('test', $ready[0]->jobId());
    }

    public function test_ready_jobs_skips_jobs_failing_condition(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('deploy-prod', [], 'environment == production'),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(0, $ready);
        $this->assertSame(JobRunStatus::SKIPPED, $run->jobRunByJobId('deploy-prod')->status());
    }

    public function test_is_complete_when_all_terminal(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $run->jobRunByJobId('build')->markAsRunning();
        $run->jobRunByJobId('build')->markAsSuccess();

        $this->assertTrue($run->isComplete());
    }

    public function test_is_not_complete_when_pending_jobs_remain(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $this->assertFalse($run->isComplete());
    }

    public function test_throws_on_invalid_status_transition(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsRunning();
    }
}
