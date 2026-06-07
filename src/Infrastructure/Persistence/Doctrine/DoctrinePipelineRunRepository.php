<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use Doctrine\DBAL\LockMode;
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
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastSuccess(string $pipelineId, Environment $env): ?PipelineRun
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(PipelineRun::class, 'r')
            ->where('r.pipelineId = :pipelineId')
            ->andWhere('r.environment = :env')
            ->andWhere('r.status = :status')
            ->setParameter('pipelineId', $pipelineId)
            ->setParameter('env', $env->value)
            ->setParameter('status', PipelineRunStatus::SUCCESS->value)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function withLock(PipelineRunId $id, callable $fn): mixed
    {
        return $this->em->wrapInTransaction(function () use ($id, $fn) {
            // Clear identity map so the FOR UPDATE fetch reads current DB state, not
            // a stale snapshot loaded earlier in Phase 1 of the same worker process.
            // Without this, parallel workers (e.g. unit-test + lint) each see the
            // other job as still RUNNING and never dispatch the downstream job.
            $this->em->clear();

            $run = $this->em->find(PipelineRun::class, $id->value, LockMode::PESSIMISTIC_WRITE);

            if ($run === null) {
                throw PipelineRunNotFoundException::forId($id->value);
            }

            return $fn($run);
        });
    }
}
