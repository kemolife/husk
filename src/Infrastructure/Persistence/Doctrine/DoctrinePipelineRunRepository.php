<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePipelineRunRepository implements PipelineRunRepositoryPort
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(PipelineRun $run): void
    {
        $this->em->persist($run);
        $this->em->flush();
    }

    public function findById(PipelineRunId $id): PipelineRun
    {
        $run = $this->em->find(PipelineRun::class, $id->value);

        if ($run === null) {
            throw PipelineRunNotFoundException::forId($id->value);
        }

        return $run;
    }

    public function findByPipelineId(string $pipelineId, int $limit = 20): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(PipelineRun::class, 'r')
            ->where('r.pipelineId = :pipelineId')
            ->setParameter('pipelineId', $pipelineId)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
