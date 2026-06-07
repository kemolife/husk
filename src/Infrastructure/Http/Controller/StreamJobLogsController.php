<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/pipeline-runs/{runId}/jobs/{jobRunId}/logs/stream',
    name: 'stream_job_logs',
    methods: ['GET'],
)]
class StreamJobLogsController
{
    public function __construct(
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly EntityManagerInterface $em,
        private readonly string $logDir = '/tmp/husk-logs',
    ) {}

    public function __invoke(string $runId, string $jobRunId): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($runId, $jobRunId) {
            $sentLength = 0;
            $maxIterations = 300;
            $iteration = 0;

            while ($iteration < $maxIterations) {
                // Resolve each iteration so we pick up the file once the executor creates it
                $logFile = $this->resolveLogFile($runId, $jobRunId);

                // Emit any new lines from file
                if ($logFile !== null && file_exists($logFile)) {
                    $content = file_get_contents($logFile);
                    if ($content !== false && strlen($content) > $sentLength) {
                        $newChunk = substr($content, $sentLength);
                        $sentLength = strlen($content);
                        foreach (explode("\n", $newChunk) as $line) {
                            if ($line !== '') {
                                echo 'data: ' . json_encode(['line' => $line], JSON_THROW_ON_ERROR) . "\n\n";
                            }
                        }
                        ob_flush();
                        flush();
                    }
                }

                // Check DB for terminal status — clear identity map first so each
                // iteration reads current state, not the first-request snapshot.
                try {
                    $this->em->clear();
                    $run = $this->runRepo->findById(new PipelineRunId($runId));
                    $jobRun = $run->jobRunById($jobRunId);
                } catch (PipelineRunNotFoundException|\InvalidArgumentException) {
                    echo "event: error\ndata: " . json_encode(['message' => 'not found'], JSON_THROW_ON_ERROR) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                if ($jobRun->status()->isTerminal()) {
                    // Fallback: emit from DB output if no log file was used
                    if ($logFile === null || !file_exists($logFile)) {
                        $dbOutput = $jobRun->output();
                        if ($dbOutput !== null && strlen($dbOutput) > $sentLength) {
                            $newChunk = substr($dbOutput, $sentLength);
                            foreach (explode("\n", $newChunk) as $line) {
                                if ($line !== '') {
                                    echo 'data: ' . json_encode(['line' => $line], JSON_THROW_ON_ERROR) . "\n\n";
                                }
                            }
                            ob_flush();
                            flush();
                        }
                    }

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

    /**
     * The log file is named by pipelineRunId (the runId passed to the executor).
     * The SSE URL includes jobRunId (UUID of the specific JobRun).
     * We need to find which pipelineRunId this jobRun belongs to — we already
     * have $runId which IS the pipelineRunId, so the file is {logDir}/{runId}.log.
     *
     * For matrix jobs (multiple JobRuns per pipeline run), all variants share
     * the same pipelineRunId-based log file, but only one is active at a time
     * since jobs run sequentially within a run. Acceptable for v1.
     */
    private function resolveLogFile(string $runId, string $jobRunId): ?string
    {
        // Primary: file named by pipelineRunId (how the executor writes it)
        $byRun = $this->logDir . '/' . $runId . '.log';
        if (file_exists($byRun)) {
            return $byRun;
        }

        // Secondary: file named by jobRunId (future per-job-run granularity)
        $byJob = $this->logDir . '/' . $jobRunId . '.log';
        if (file_exists($byJob)) {
            return $byJob;
        }

        // No file yet — executor hasn't started or fallback to DB
        return null;
    }
}
