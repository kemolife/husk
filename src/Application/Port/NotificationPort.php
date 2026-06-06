<?php

namespace App\Application\Port;

use App\Domain\Pipeline\NotificationConfig;

interface NotificationPort
{
    public function notify(
        string $event,
        string $runId,
        string $pipelineId,
        string $status,
        string $environment,
        NotificationConfig $config,
    ): void;
}
