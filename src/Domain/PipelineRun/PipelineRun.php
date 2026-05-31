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

    #[ORM\OneToMany(targetEntity: JobRun::class, mappedBy: 'pipelineRun', cascade: ['persist', 'remove'])]
    private Collection $jobRuns;

    public function __construct(PipelineRunId $id, Pipeline $pipeline, Environment $environment)
    {
        $this->id = $id->value;
        $this->pipelineId = $pipeline->id()->value;
        $this->environment = $environment;
        $this->status = PipelineRunStatus::PENDING;
        $this->jobRuns = new ArrayCollection();

        foreach ($pipeline->jobs() as $job) {
            $jobRun = new JobRun(Uuid::v4()->toRfc4122(), $job->id, $job->type);
            $jobRun->setPipelineRun($this);
            $this->jobRuns->add($jobRun);
        }
    }

    public function id(): PipelineRunId { return new PipelineRunId($this->id); }
    public function pipelineId(): string { return $this->pipelineId; }
    public function status(): PipelineRunStatus { return $this->status; }
    public function environment(): Environment { return $this->environment; }

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

            if ($this->allNeedsSatisfied($job->needs)) {
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
        $this->status = PipelineRunStatus::SUCCESS;
    }

    public function markAsFailed(): void
    {
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

    /** @param string[] $needs */
    private function anyNeedsSkipped(array $needs): bool
    {
        foreach ($needs as $neededJobId) {
            foreach ($this->jobRuns as $jr) {
                if ($jr->jobId() === $neededJobId && $jr->status() === JobRunStatus::SKIPPED) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @param string[] $needs */
    private function allNeedsSatisfied(array $needs): bool
    {
        foreach ($needs as $neededJobId) {
            $satisfied = false;
            foreach ($this->jobRuns as $jr) {
                if ($jr->jobId() === $neededJobId && $jr->status() === JobRunStatus::SUCCESS) {
                    $satisfied = true;
                    break;
                }
            }
            if (!$satisfied) {
                return false;
            }
        }
        return true;
    }
}
