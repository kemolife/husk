<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\PipelineRunRepositoryPort;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ListPipelinesController
{
    public function __construct(
        private readonly string $pipelinesDir,
        private readonly PipelineRunRepositoryPort $runRepo,
    ) {}

    #[Route('/pipelines', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $files = glob($this->pipelinesDir . '/*.yaml') ?: [];

        $pipelines = array_map(function (string $file): array {
            $id = basename($file, '.yaml');
            $data = \Symfony\Component\Yaml\Yaml::parseFile($file);

            $runs = $this->runRepo->findByPipelineId($id, 1);
            $lastRun = $runs[0] ?? null;

            return [
                'id' => $id,
                'name' => $data['name'] ?? $id,
                'last_run' => $lastRun ? [
                    'id' => $lastRun->id()->value,
                    'status' => $lastRun->status()->value,
                    'jobs' => array_map(fn($jr) => [
                        'job_id' => $jr->jobId(),
                        'status' => $jr->status()->value,
                    ], $lastRun->jobRuns()),
                ] : null,
            ];
        }, $files);

        return new JsonResponse(array_values($pipelines));
    }
}
