<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\RollbackPipeline\RollbackPipelineCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class RollbackPipelineController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipelines/{id}/rollback', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $environment = $body['environment'] ?? 'production';
        $newRunId = Uuid::v4()->toRfc4122();

        $this->bus->dispatch(new RollbackPipelineCommand($id, $environment, $newRunId));

        return new JsonResponse(['runId' => $newRunId], Response::HTTP_ACCEPTED);
    }
}
