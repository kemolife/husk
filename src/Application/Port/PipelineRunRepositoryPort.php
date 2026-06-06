<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use App\Domain\Shared\Environment;

interface PipelineRunRepositoryPort
{
    public function save(PipelineRun $run): void;

    /** @throws PipelineRunNotFoundException */
    public function findById(PipelineRunId $id): PipelineRun;

    /** @return PipelineRun[] */
    public function findByPipelineId(string $pipelineId, int $limit = 20): array;

    public function findLastSuccess(string $pipelineId, Environment $env): ?PipelineRun;

    /**
     * Acquire an exclusive row lock on the pipeline run, execute $fn with the fresh locked
     * instance, persist, and commit — all in one transaction.
     * Serializes concurrent workers that finish jobs on the same run simultaneously.
     *
     * @template T
     * @param callable(PipelineRun): T $fn
     * @return T
     */
    public function withLock(PipelineRunId $id, callable $fn): mixed;
}
