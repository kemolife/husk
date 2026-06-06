<?php

namespace App\Infrastructure\Persistence\InMemory;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;

class InMemoryPipelineRunRepository implements PipelineRunRepositoryPort
{
    /** @var PipelineRun[] */
    private array $store = [];

    public function save(PipelineRun $run): void
    {
        $this->store[$run->id()->value] = $run;
    }

    public function findById(PipelineRunId $id): PipelineRun
    {
        if (!isset($this->store[$id->value])) {
            throw PipelineRunNotFoundException::forId($id->value);
        }
        return $this->store[$id->value];
    }

    public function findByPipelineId(string $pipelineId, int $limit = 20): array
    {
        $results = array_filter(
            $this->store,
            fn(PipelineRun $r) => $r->pipelineId() === $pipelineId
        );
        return array_slice(array_values($results), 0, $limit);
    }

    public function findLastSuccess(string $pipelineId, Environment $env): ?PipelineRun
    {
        $results = array_filter(
            $this->store,
            fn(PipelineRun $r) => $r->pipelineId() === $pipelineId
                && $r->environment() === $env
                && $r->status() === PipelineRunStatus::SUCCESS,
        );

        if (empty($results)) {
            return null;
        }

        usort($results, fn(PipelineRun $a, PipelineRun $b) => $b->createdAt() <=> $a->createdAt());

        return $results[0];
    }

    public function withLock(PipelineRunId $id, callable $fn): mixed
    {
        $run = $this->findById($id);
        $result = $fn($run);
        $this->save($run);
        return $result;
    }
}
