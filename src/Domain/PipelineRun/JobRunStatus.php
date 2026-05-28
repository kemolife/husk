<?php

namespace App\Domain\PipelineRun;

enum JobRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case SKIPPED = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::SKIPPED], true);
    }
}
