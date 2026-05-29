<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class TriggerPipelineController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipelines/{id}/run', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $content = $request->getContent();
        $body = $content !== '' ? json_decode($content, true, 512, JSON_THROW_ON_ERROR) : [];
        $environment = $body['environment'] ?? 'staging';

        $runId = PipelineRunId::generate();
        $this->bus->dispatch(new TriggerPipelineCommand($runId->value, $id, $environment));

        return new JsonResponse(['pipeline_run_id' => $runId->value], Response::HTTP_ACCEPTED);
    }
}
