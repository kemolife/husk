<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\PipelineEventRepositoryPort;
use App\Domain\PipelineRun\PipelineEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ListPipelineEventsController
{
    public function __construct(private readonly PipelineEventRepositoryPort $eventRepo) {}

    #[Route('/pipeline-runs/{runId}/events', methods: ['GET'])]
    public function __invoke(string $runId): JsonResponse
    {
        $events = $this->eventRepo->findByRunId($runId);

        return new JsonResponse([
            'events' => array_map(fn(PipelineEvent $e) => [
                'id' => $e->id(),
                'type' => $e->type()->value,
                'payload' => $e->payload(),
                'occurredAt' => $e->occurredAt()->format(\DateTimeInterface::ATOM),
            ], $events),
        ]);
    }
}
