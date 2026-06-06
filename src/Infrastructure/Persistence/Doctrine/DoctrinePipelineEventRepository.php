<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\PipelineEventRepositoryPort;
use App\Domain\PipelineRun\PipelineEvent;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePipelineEventRepository implements PipelineEventRepositoryPort
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function record(PipelineEvent $event): void
    {
        $this->em->persist($event);
        $this->em->flush();
    }

    public function findByRunId(string $runId): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from(PipelineEvent::class, 'e')
            ->where('e.pipelineRunId = :runId')
            ->setParameter('runId', $runId)
            ->orderBy('e.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
