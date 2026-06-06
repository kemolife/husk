<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/webhooks/github', name: 'webhook_github', methods: ['POST'])]
class GithubWebhookController
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly MessageBusInterface $bus,
        private readonly string $webhookSecret,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if ($this->webhookSecret !== '') {
            $signature = $request->headers->get('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $this->webhookSecret);
            if (!hash_equals($expected, $signature)) {
                throw new AccessDeniedHttpException('Invalid webhook signature');
            }
        }

        $event = $request->headers->get('X-GitHub-Event', '');
        if (!in_array($event, ['push', 'pull_request'], true)) {
            return new JsonResponse(['status' => 'ignored', 'event' => $event]);
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        $repoName = $payload['repository']['name'] ?? null;
        if ($repoName === null) {
            throw new BadRequestHttpException('Missing repository.name in payload');
        }

        try {
            $this->pipelineRepo->findById(new PipelineId($repoName));
        } catch (PipelineNotFoundException) {
            return new JsonResponse(['status' => 'no_pipeline', 'repo' => $repoName]);
        }

        $branch = match ($event) {
            'push' => str_replace('refs/heads/', '', $payload['ref'] ?? ''),
            'pull_request' => $payload['pull_request']['head']['ref'] ?? '',
            default => '',
        };

        $runId = Uuid::v4()->toRfc4122();
        $this->bus->dispatch(new TriggerPipelineCommand(
            pipelineRunId: $runId,
            pipelineId: $repoName,
            environment: 'development',
        ));

        return new JsonResponse(['runId' => $runId, 'branch' => $branch, 'repo' => $repoName], 202);
    }
}
