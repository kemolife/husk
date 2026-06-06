<?php

namespace App\Infrastructure\Notification;

use App\Application\Port\NotificationPort;
use App\Domain\Pipeline\NotificationConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SlackNotificationAdapter implements NotificationPort
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
        if ($config->slackWebhookUrl === null || !$config->wantsSlack($status)) {
            return;
        }

        $emoji = $status === 'success' ? ':white_check_mark:' : ':x:';
        $text = "{$emoji} Pipeline *{$pipelineId}* [{$environment}] — {$status} (run `{$runId}`)";

        $payload = ['text' => $text];
        if ($config->slackChannel !== null) {
            $payload['channel'] = $config->slackChannel;
        }

        $this->httpClient->request('POST', $config->slackWebhookUrl, [
            'json' => $payload,
        ]);
    }
}
