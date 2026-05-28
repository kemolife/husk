<?php

namespace App\Domain\PipelineRun;

use App\Domain\Pipeline\JobType;
use App\Domain\Shared\InvalidStatusTransitionException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'job_runs')]
class JobRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $jobId;

    #[ORM\Column(type: 'string', enumType: JobRunStatus::class)]
    private JobRunStatus $status;

    #[ORM\Column(type: 'string', enumType: JobType::class)]
    private JobType $type;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\PipelineRun\PipelineRun', inversedBy: 'jobRuns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PipelineRun $pipelineRun;

    public function __construct(string $id, string $jobId, JobType $type)
    {
        $this->id = $id;
        $this->jobId = $jobId;
        $this->type = $type;
        $this->status = JobRunStatus::PENDING;
    }

    public function id(): string { return $this->id; }
    public function jobId(): string { return $this->jobId; }
    public function status(): JobRunStatus { return $this->status; }
    public function isPending(): bool { return $this->status === JobRunStatus::PENDING; }
    public function isApproval(): bool { return $this->type === JobType::APPROVAL; }

    public function setPipelineRun(PipelineRun $run): void
    {
        $this->pipelineRun = $run;
    }

    public function markAsRunning(): void
    {
        if ($this->status !== JobRunStatus::PENDING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'running');
        }
        $this->status = JobRunStatus::RUNNING;
    }

    public function markAsSuccess(): void
    {
        if (!in_array($this->status, [JobRunStatus::RUNNING, JobRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'success');
        }
        $this->status = JobRunStatus::SUCCESS;
    }

    public function markAsFailed(): void
    {
        if (!in_array($this->status, [JobRunStatus::RUNNING, JobRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'failed');
        }
        $this->status = JobRunStatus::FAILED;
    }

    public function markAsAwaitingApproval(): void
    {
        if ($this->status !== JobRunStatus::RUNNING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'awaiting_approval');
        }
        $this->status = JobRunStatus::AWAITING_APPROVAL;
    }

    public function markAsSkipped(): void
    {
        if ($this->status !== JobRunStatus::PENDING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'skipped');
        }
        $this->status = JobRunStatus::SKIPPED;
    }
}
