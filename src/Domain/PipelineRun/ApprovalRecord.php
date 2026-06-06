<?php

namespace App\Domain\PipelineRun;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'approval_records')]
class ApprovalRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $pipelineRunId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $jobRunId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $actorId;

    #[ORM\Column(type: 'boolean')]
    private bool $approved;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $decidedAt;

    public function __construct(
        string $id,
        string $pipelineRunId,
        string $jobRunId,
        string $actorId,
        bool $approved,
    ) {
        $this->id = $id;
        $this->pipelineRunId = $pipelineRunId;
        $this->jobRunId = $jobRunId;
        $this->actorId = $actorId;
        $this->approved = $approved;
        $this->decidedAt = new \DateTimeImmutable();
    }

    public function id(): string { return $this->id; }
    public function pipelineRunId(): string { return $this->pipelineRunId; }
    public function jobRunId(): string { return $this->jobRunId; }
    public function actorId(): string { return $this->actorId; }
    public function approved(): bool { return $this->approved; }
    public function decidedAt(): \DateTimeImmutable { return $this->decidedAt; }
}
