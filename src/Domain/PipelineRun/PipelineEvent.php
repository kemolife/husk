<?php

namespace App\Domain\PipelineRun;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pipeline_events')]
class PipelineEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $pipelineRunId;

    #[ORM\Column(type: 'string', enumType: PipelineEventType::class)]
    private PipelineEventType $type;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        string $id,
        string $pipelineRunId,
        PipelineEventType $type,
        ?array $payload = null,
    ) {
        $this->id = $id;
        $this->pipelineRunId = $pipelineRunId;
        $this->type = $type;
        $this->payload = $payload;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function id(): string { return $this->id; }
    public function pipelineRunId(): string { return $this->pipelineRunId; }
    public function type(): PipelineEventType { return $this->type; }
    public function payload(): ?array { return $this->payload; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
