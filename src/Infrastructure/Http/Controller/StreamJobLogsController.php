<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/pipeline-runs/{runId}/jobs/{jobId}/logs/stream',
    name: 'stream_job_logs',
    methods: ['GET'],
)]
class StreamJobLogsController
{
    public function __construct(private readonly PipelineRunRepositoryPort $runRepo) {}

    public function __invoke(string $runId, string $jobId): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($runId, $jobId) {
            $sentLength = 0;
            $maxIterations = 300;
            $iteration = 0;

            while ($iteration < $maxIterations) {
                try {
                    $run = $this->runRepo->findById(new PipelineRunId($runId));
                    $jobRun = $run->jobRunByJobId($jobId);
                } catch (PipelineRunNotFoundException|\InvalidArgumentException) {
                    echo "event: error\ndata: " . json_encode(['message' => 'not found'], JSON_THROW_ON_ERROR) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                $output = $jobRun->output();
                if ($output !== null && strlen($output) > $sentLength) {
                    $newChunk = substr($output, $sentLength);
                    $sentLength = strlen($output);
                    foreach (explode("\n", $newChunk) as $line) {
                        echo 'data: ' . json_encode(['line' => $line], JSON_THROW_ON_ERROR) . "\n\n";
                    }
                    ob_flush();
                    flush();
                }

                if ($jobRun->status()->isTerminal()) {
                    echo "event: done\ndata: " . json_encode(['status' => $jobRun->status()->value], JSON_THROW_ON_ERROR) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                sleep(1);
                $iteration++;
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
