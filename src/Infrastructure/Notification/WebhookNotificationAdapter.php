<?php

namespace App\Infrastructure\Notification;

use App\Application\Port\NotificationPort;
use App\Domain\Pipeline\NotificationConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookNotificationAdapter implements NotificationPort
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function notify(
        string $event,
        string $runId,
        string $pipelineId,
        string $status,
        string $environment,
        NotificationConfig $config,
    ): void {
        if ($config->webhookUrl === null || !$config->wantsWebhook($status)) {
            return;
        }

        $this->httpClient->request('POST', $config->webhookUrl, [
            'json' => [
                'event' => $event,
                'runId' => $runId,
                'pipelineId' => $pipelineId,
                'status' => $status,
                'environment' => $environment,
            ],
        ]);
    }
}
