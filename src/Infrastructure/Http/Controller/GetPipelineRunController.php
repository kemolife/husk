<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Query\GetPipelineRunStatus\GetPipelineRunStatusQuery;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

class GetPipelineRunController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipeline-runs/{runId}', methods: ['GET'])]
    public function __invoke(string $runId): JsonResponse
    {
        try {
            $envelope = $this->bus->dispatch(new GetPipelineRunStatusQuery($runId));
            $view = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse([
                'id' => $view->id,
                'pipeline_id' => $view->pipelineId,
                'status' => $view->status,
                'environment' => $view->environment,
                'jobs' => array_map(fn($j) => [
                    'id' => $j->id,
                    'job_id' => $j->jobId,
                    'status' => $j->status,
                    'output' => $j->output,
                ], $view->jobs),
            ]);
        } catch (PipelineRunNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
