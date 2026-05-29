<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApprovePipelineJobController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipeline-runs/{runId}/jobs/{jobId}/approve', methods: ['POST'])]
    public function approve(string $runId, string $jobId): JsonResponse
    {
        $this->bus->dispatch(new ApprovePipelineJobCommand($runId, $jobId, true));
        return new JsonResponse(['status' => 'approved'], Response::HTTP_OK);
    }

    #[Route('/pipeline-runs/{runId}/jobs/{jobId}/reject', methods: ['POST'])]
    public function reject(string $runId, string $jobId): JsonResponse
    {
        $this->bus->dispatch(new ApprovePipelineJobCommand($runId, $jobId, false));
        return new JsonResponse(['status' => 'rejected'], Response::HTTP_OK);
    }
}
