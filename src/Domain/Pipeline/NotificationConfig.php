<?php

namespace App\Domain\Pipeline;

final readonly class NotificationConfig
{
    /**
     * @param string[] $slackOn
     * @param string[] $webhookOn
     */
    public function __construct(
        public readonly ?string $slackWebhookUrl = null,
        public readonly ?string $slackChannel = null,
        public readonly array $slackOn = ['success', 'failure'],
        public readonly ?string $webhookUrl = null,
        public readonly array $webhookOn = ['success', 'failure'],
    ) {}

    public function wantsSlack(string $status): bool
    {
        return $this->slackWebhookUrl !== null && in_array($status, $this->slackOn, true);
    }

    public function wantsWebhook(string $status): bool
    {
        return $this->webhookUrl !== null && in_array($status, $this->webhookOn, true);
    }
}
