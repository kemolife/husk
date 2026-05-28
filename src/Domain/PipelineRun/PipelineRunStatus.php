<?php

namespace App\Domain\PipelineRun;

enum PipelineRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case AWAITING_APPROVAL = 'awaiting_approval';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED], true);
    }
}
