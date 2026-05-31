<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Application\Query\GetPipelineRunStatus\JobRunStatusView;
use App\Application\Query\GetPipelineRunStatus\PipelineRunStatusView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ListPipelineRunsController
{
    public function __construct(private readonly PipelineRunRepositoryPort $runRepo) {}

    #[Route('/pipeline-runs', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $pipelineId = $request->query->getString('pipeline_id');

        if ($pipelineId === '') {
            return new JsonResponse(['error' => 'pipeline_id query param required'], 400);
        }

        $runs = $this->runRepo->findByPipelineId($pipelineId);

        $data = array_map(function ($run) {
            $jobs = array_map(
                fn($jr) => new JobRunStatusView($jr->id(), $jr->jobId(), $jr->status()->value, $jr->output()),
                $run->jobRuns()
            );
            $view = new PipelineRunStatusView(
                $run->id()->value,
                $run->pipelineId(),
                $run->status()->value,
                $run->environment()->value,
                $jobs,
            );
            return [
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
            ];
        }, $runs);

        return new JsonResponse($data);
    }
}
