<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\ApprovalRecord;

interface ApprovalRecordRepositoryPort
{
    public function save(ApprovalRecord $record): void;

    /** @return ApprovalRecord[] */
    public function findByRunId(string $runId): array;
}
