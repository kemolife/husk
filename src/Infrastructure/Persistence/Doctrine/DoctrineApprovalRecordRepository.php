<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\ApprovalRecordRepositoryPort;
use App\Domain\PipelineRun\ApprovalRecord;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineApprovalRecordRepository implements ApprovalRecordRepositoryPort
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(ApprovalRecord $record): void
    {
        $this->em->persist($record);
        $this->em->flush();
    }

    public function findByRunId(string $runId): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(ApprovalRecord::class, 'r')
            ->where('r.pipelineRunId = :runId')
            ->setParameter('runId', $runId)
            ->orderBy('r.decidedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
