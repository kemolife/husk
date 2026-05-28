<?php

namespace App\Tests\Domain\PipelineRun;

use App\Domain\Pipeline\JobType;
use App\Domain\PipelineRun\JobRun;
use App\Domain\PipelineRun\JobRunStatus;
use App\Domain\Shared\InvalidStatusTransitionException;
use PHPUnit\Framework\TestCase;

class JobRunTest extends TestCase
{
    private function makeJobRun(JobType $type = JobType::SCRIPT): JobRun
    {
        return new JobRun('run-id-1', 'build', $type);
    }

    public function test_starts_as_pending(): void
    {
        $this->assertSame(JobRunStatus::PENDING, $this->makeJobRun()->status());
    }

    public function test_pending_transitions_to_running(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $this->assertSame(JobRunStatus::RUNNING, $run->status());
    }

    public function test_running_transitions_to_success(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsSuccess();
        $this->assertSame(JobRunStatus::SUCCESS, $run->status());
    }

    public function test_running_transitions_to_failed(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsFailed();
        $this->assertSame(JobRunStatus::FAILED, $run->status());
    }

    public function test_running_approval_job_transitions_to_awaiting_approval(): void
    {
        $run = $this->makeJobRun(JobType::APPROVAL);
        $run->markAsRunning();
        $run->markAsAwaitingApproval();
        $this->assertSame(JobRunStatus::AWAITING_APPROVAL, $run->status());
    }

    public function test_awaiting_approval_transitions_to_success(): void
    {
        $run = $this->makeJobRun(JobType::APPROVAL);
        $run->markAsRunning();
        $run->markAsAwaitingApproval();
        $run->markAsSuccess();
        $this->assertSame(JobRunStatus::SUCCESS, $run->status());
    }

    public function test_pending_transitions_to_skipped(): void
    {
        $run = $this->makeJobRun();
        $run->markAsSkipped();
        $this->assertSame(JobRunStatus::SKIPPED, $run->status());
    }

    public function test_throws_on_invalid_transition_success_to_running(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsSuccess();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsRunning();
    }

    public function test_throws_on_invalid_transition_running_to_skipped(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsSkipped();
    }
}
