<?php

namespace App\Domain\PipelineRun;

enum PipelineEventType: string
{
    case TRIGGERED = 'triggered';
    case JOB_STARTED = 'job_started';
    case JOB_COMPLETED = 'job_completed';
    case APPROVAL_REQUESTED = 'approval_requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RUN_COMPLETED = 'run_completed';
}
