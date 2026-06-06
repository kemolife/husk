<?php

namespace App\Domain\PipelineRun;

use App\Domain\Pipeline\Pipeline;
use App\Domain\Shared\Environment;
use App\Domain\Shared\InvalidStatusTransitionException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pipeline_runs')]
class PipelineRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $pipelineId;

    #[ORM\Column(type: 'string', enumType: PipelineRunStatus::class)]
    private PipelineRunStatus $status;

    #[ORM\Column(type: 'string', enumType: Environment::class)]
    private Environment $environment;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: JobRun::class, mappedBy: 'pipelineRun', cascade: ['persist', 'remove'])]
    private Collection $jobRuns;

    public function __construct(PipelineRunId $id, Pipeline $pipeline, Environment $environment)
    {
        $this->id = $id->value;
        $this->pipelineId = $pipeline->id()->value;
        $this->environment = $environment;
        $this->status = PipelineRunStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
        $this->jobRuns = new ArrayCollection();

        foreach ($pipeline->jobs() as $job) {
            if ($job->matrix !== null) {
                foreach ($job->matrix->combinations() as $combo) {
                    $jobRun = new JobRun(Uuid::v4()->toRfc4122(), $job->id, $job->type, $combo);
                    $jobRun->setPipelineRun($this);
                    $this->jobRuns->add($jobRun);
                }
            } else {
                $jobRun = new JobRun(Uuid::v4()->toRfc4122(), $job->id, $job->type);
                $jobRun->setPipelineRun($this);
                $this->jobRuns->add($jobRun);
            }
        }
    }

    public function id(): PipelineRunId { return new PipelineRunId($this->id); }
    public function pipelineId(): string { return $this->pipelineId; }
    public function status(): PipelineRunStatus { return $this->status; }
    public function environment(): Environment { return $this->environment; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return JobRun[] */
    public function jobRuns(): array { return $this->jobRuns->toArray(); }

    public function jobRunByJobId(string $jobId): JobRun
    {
        foreach ($this->jobRuns as $jr) {
            if ($jr->jobId() === $jobId) {
                return $jr;
            }
        }
        throw new \InvalidArgumentException("JobRun for job '{$jobId}' not found");
    }

    public function jobRunById(string $id): JobRun
    {
        foreach ($this->jobRuns as $jr) {
            if ($jr->id() === $id) {
                return $jr;
            }
        }
        throw new \InvalidArgumentException("JobRun '{$id}' not found");
    }

    /** @return JobRun[] */
    public function readyJobs(Pipeline $pipeline): array
    {
        $ready = [];

        foreach ($this->jobRuns as $jr) {
            if (!$jr->isPending()) {
                continue;
            }

            $job = $pipeline->job($jr->jobId());

            if (!$this->conditionMatches($job->condition) || $this->anyNeedsSkipped($job->needs)) {
                $jr->markAsSkipped();
                continue;
            }

            if ($this->allNeedsSatisfied($job->needs, $pipeline)) {
                $ready[] = $jr;
            }
        }

        return $ready;
    }

    public function isComplete(): bool
    {
        foreach ($this->jobRuns as $jr) {
            if (!$jr->status()->isTerminal()) {
                return false;
            }
        }
        return true;
    }

    public function markAsRunning(): void
    {
        if (!in_array($this->status, [PipelineRunStatus::PENDING, PipelineRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('PipelineRun', $this->status->value, 'running');
        }
        $this->status = PipelineRunStatus::RUNNING;
    }

    public function markAsSuccess(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidStatusTransitionException::for('PipelineRun', $this->status->value, 'success');
        }
        $this->status = PipelineRunStatus::SUCCESS;
    }

    public function markAsFailed(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidStatusTransitionException::for('PipelineRun', $this->status->value, 'failed');
        }
        $this->status = PipelineRunStatus::FAILED;
    }

    public function markAsAwaitingApproval(): void
    {
        if ($this->status !== PipelineRunStatus::RUNNING) {
            throw InvalidStatusTransitionException::for('PipelineRun', $this->status->value, 'awaiting_approval');
        }
        $this->status = PipelineRunStatus::AWAITING_APPROVAL;
    }

    private function conditionMatches(?string $condition): bool
    {
        if ($condition === null) {
            return true;
        }

        if (preg_match('/^environment\s*==\s*(\w+)$/', $condition, $matches)) {
            return $this->environment->value === strtolower($matches[1]);
        }

        return true;
    }

    /**
     * @param string[] $needs
     * All matrix variants of a needed job must be SKIPPED for the need to be considered skipped.
     */
    private function anyNeedsSkipped(array $needs): bool
    {
        foreach ($needs as $neededJobId) {
            $variants = array_filter(
                $this->jobRuns->toArray(),
                fn(JobRun $jr) => $jr->jobId() === $neededJobId,
            );

            if (!empty($variants)) {
                $allSkipped = count(array_filter($variants, fn(JobRun $jr) => $jr->status() === JobRunStatus::SKIPPED)) === count($variants);
                if ($allSkipped) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string[] $needs
     * All matrix variants of each needed job must be satisfied (SUCCESS, or FAILED+continueOnError).
     */
    private function allNeedsSatisfied(array $needs, Pipeline $pipeline): bool
    {
        foreach ($needs as $neededJobId) {
            $variants = array_filter(
                $this->jobRuns->toArray(),
                fn(JobRun $jr) => $jr->jobId() === $neededJobId,
            );

            if (empty($variants)) {
                return false;
            }

            $job = $pipeline->job($neededJobId);
            foreach ($variants as $jr) {
                if ($jr->status() === JobRunStatus::SUCCESS) {
                    continue;
                }
                if ($jr->status() === JobRunStatus::FAILED && $job->continueOnError) {
                    continue;
                }
                return false;
            }
        }
        return true;
    }
}
