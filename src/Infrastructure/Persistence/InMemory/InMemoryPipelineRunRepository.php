<?php

namespace App\Infrastructure\Persistence\InMemory;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;

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
}
