<?php

namespace App\Infrastructure\Persistence\Yaml;

use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\MatrixStrategy;
use App\Domain\Pipeline\NotificationConfig;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
use App\Domain\Pipeline\RetryPolicy;
use App\Domain\Pipeline\Schedule;
use Symfony\Component\Yaml\Yaml;

class YamlFilePipelineRepository implements PipelineRepositoryPort
{
    public function __construct(private readonly string $pipelinesDir) {}

    public function findById(PipelineId $id): Pipeline
    {
        $file = $this->pipelinesDir . '/' . $id->value . '.yaml';

        if (!file_exists($file)) {
            throw PipelineNotFoundException::forId($id->value);
        }

        return $this->parseFile($id->value, $file);
    }

    private function parseFile(string $id, string $file): Pipeline
    {
        $data = Yaml::parseFile($file);

        $jobs = [];
        foreach ($data['jobs'] ?? [] as $jobId => $jobData) {
            $type = isset($jobData['type']) && $jobData['type'] === 'approval'
                ? JobType::APPROVAL
                : JobType::SCRIPT;

            $needs = isset($jobData['needs'])
                ? (array) $jobData['needs']
                : [];

            $retry = null;
            if (isset($jobData['retry'])) {
                $retry = new RetryPolicy(
                    maxAttempts: (int) ($jobData['retry']['max'] ?? 1),
                    delaySeconds: (int) ($jobData['retry']['delay'] ?? 0),
                );
            }

            $matrix = null;
            if (isset($jobData['matrix']) && is_array($jobData['matrix'])) {
                $matrix = new MatrixStrategy($jobData['matrix']);
            }

            $jobs[] = new Job(
                id: $jobId,
                type: $type,
                image: $jobData['image'] ?? null,
                script: $jobData['script'] ?? null,
                needs: $needs,
                condition: $jobData['if'] ?? null,
                continueOnError: (bool) ($jobData['continue_on_error'] ?? false),
                timeoutSeconds: isset($jobData['timeout']) ? (int) $jobData['timeout'] : null,
                retry: $retry,
                secretNames: (array) ($jobData['secrets'] ?? []),
                matrix: $matrix,
            );
        }

        $notifications = null;
        if (isset($data['notifications']) && is_array($data['notifications'])) {
            $n = $data['notifications'];
            $notifications = new NotificationConfig(
                slackWebhookUrl: $n['slack']['webhook_url'] ?? null,
                slackChannel: $n['slack']['channel'] ?? null,
                slackOn: (array) ($n['slack']['on'] ?? ['success', 'failure']),
                webhookUrl: $n['webhook']['url'] ?? null,
                webhookOn: (array) ($n['webhook']['on'] ?? ['success', 'failure']),
            );
        }

        $schedules = [];
        foreach ($data['on']['schedule'] ?? [] as $entry) {
            $schedules[] = new Schedule(
                cron: $entry['cron'],
                environment: $entry['environment'] ?? 'production',
            );
        }

        $pushBranches = (array) ($data['on']['push']['branches'] ?? []);

        return new Pipeline(new PipelineId($id), $data['name'] ?? $id, $jobs, $notifications, $schedules, $pushBranches);
    }

    /** @return Pipeline[] */
    public function findAll(): array
    {
        $pipelines = [];
        foreach (glob($this->pipelinesDir . '/*.yaml') ?: [] as $file) {
            $id = basename($file, '.yaml');
            $pipelines[] = $this->parseFile($id, $file);
        }
        return $pipelines;
    }
}
