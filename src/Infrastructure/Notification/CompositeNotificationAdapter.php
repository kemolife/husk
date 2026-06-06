<?php

namespace App\Infrastructure\Notification;

use App\Application\Port\NotificationPort;
use App\Domain\Pipeline\NotificationConfig;

class CompositeNotificationAdapter implements NotificationPort
{
    public function __construct(
        private readonly SlackNotificationAdapter $slack,
        private readonly WebhookNotificationAdapter $webhook,
    ) {}

    public function notify(
        string $event,
        string $runId,
        string $pipelineId,
        string $status,
        string $environment,
        NotificationConfig $config,
    ): void {
        $this->slack->notify($event, $runId, $pipelineId, $status, $environment, $config);
        $this->webhook->notify($event, $runId, $pipelineId, $status, $environment, $config);
    }
}
