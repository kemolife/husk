<?php

namespace App\Infrastructure\Executor;

use App\Application\Port\ExecutorPort;
use App\Application\Port\JobResult;
use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DockerSocketExecutorAdapter implements ExecutorPort
{
    public function __construct(private readonly string $socketPath = '/var/run/docker.sock') {}

    public function run(Job $job, Environment $environment, string $runId): JobResult
    {
        $client = $this->createClient();
        $volumeName = "pipeline-run-{$runId}";
        $image = $job->image ?? 'alpine:latest';
        $containerId = null;

        try {
            $this->ensureVolume($client, $volumeName);
            $this->pullImage($client, $image);
            $containerId = $this->createContainer($client, $job, $image, $volumeName, $environment);
            $this->startContainer($client, $containerId);
            $exitCode = $this->waitContainer($client, $containerId);
            $output = $this->getLogs($client, $containerId);
            $this->removeContainer($client, $containerId);

            return $exitCode === 0
                ? JobResult::success($output)
                : JobResult::failure($output);
        } catch (\Throwable $e) {
            if ($containerId !== null) {
                try { $this->removeContainer($client, $containerId); } catch (\Throwable) {}
            }
            return JobResult::failure($e->getMessage());
        }
    }

    private function createClient(): HttpClientInterface
    {
        return new CurlHttpClient([
            'base_uri' => 'http://localhost',
            'extra' => ['curl' => [\CURLOPT_UNIX_SOCKET_PATH => $this->socketPath]],
        ]);
    }

    private function ensureVolume(HttpClientInterface $client, string $name): void
    {
        $client->request('POST', '/volumes/create', ['json' => ['Name' => $name]])->getContent(false);
    }

    private function pullImage(HttpClientInterface $client, string $image): void
    {
        $parts = explode(':', $image, 2);
        $fromImage = $parts[0];
        $tag = $parts[1] ?? 'latest';
        $client->request('POST', "/images/create?fromImage={$fromImage}&tag={$tag}")->getContent(false);
    }

    private function createContainer(
        HttpClientInterface $client,
        Job $job,
        string $image,
        string $volumeName,
        Environment $environment,
    ): string {
        $script = $job->script ?? 'true';
        $response = $client->request('POST', '/containers/create', [
            'json' => [
                'Image' => $image,
                'Cmd' => ['/bin/sh', '-c', $script],
                'Env' => ["PIPELINE_ENV={$environment->value}"],
                'WorkingDir' => '/workspace',
                'HostConfig' => [
                    'Binds' => ["{$volumeName}:/workspace"],
                    'AutoRemove' => false,
                ],
            ],
        ]);
        return $response->toArray()['Id'];
    }

    private function startContainer(HttpClientInterface $client, string $containerId): void
    {
        $client->request('POST', "/containers/{$containerId}/start")->getContent(false);
    }

    private function waitContainer(HttpClientInterface $client, string $containerId): int
    {
        $response = $client->request('POST', "/containers/{$containerId}/wait");
        return $response->toArray()['StatusCode'] ?? 1;
    }

    private function getLogs(HttpClientInterface $client, string $containerId): string
    {
        $response = $client->request('GET', "/containers/{$containerId}/logs?stdout=1&stderr=1&timestamps=0");
        return $this->stripDockerLogHeaders($response->getContent());
    }

    private function removeContainer(HttpClientInterface $client, string $containerId): void
    {
        $client->request('DELETE', "/containers/{$containerId}?force=1")->getContent(false);
    }

    /**
     * Docker multiplexed log stream: each chunk prefixed with 8-byte header.
     * Bytes 0: stream type (1=stdout, 2=stderr). Bytes 4-7: payload size (big-endian uint32).
     */
    private function stripDockerLogHeaders(string $raw): string
    {
        $output = '';
        $offset = 0;
        $len = strlen($raw);

        while ($offset + 8 <= $len) {
            $size = unpack('N', substr($raw, $offset + 4, 4))[1];
            $output .= substr($raw, $offset + 8, $size);
            $offset += 8 + $size;
        }

        return $output !== '' ? $output : $raw;
    }
}
