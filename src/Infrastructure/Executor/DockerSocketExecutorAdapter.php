<?php

namespace App\Infrastructure\Executor;

use App\Application\Port\ExecutorPort;
use App\Application\Port\JobResult;
use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

class DockerSocketExecutorAdapter implements ExecutorPort
{
    public function __construct(private readonly string $socketPath = '/var/run/docker.sock') {}

    public function run(Job $job, Environment $environment, string $runId): JobResult
    {
        $volumeName = "pipeline-run-{$runId}";
        $image = $job->image ?? 'alpine:latest';
        $containerId = null;

        try {
            $this->ensureVolume($volumeName);
            $this->pullImage($image);
            $containerId = $this->createContainer($job, $image, $volumeName, $environment);
            $this->startContainer($containerId);
            $exitCode = $this->waitContainer($containerId);
            $output = "Pulling image: {$image}\n---\n" . $this->getLogs($containerId);
            $this->removeContainer($containerId);

            return $exitCode === 0
                ? JobResult::success($output)
                : JobResult::failure($output);
        } catch (\Throwable $e) {
            if ($containerId !== null) {
                try { $this->removeContainer($containerId); } catch (\Throwable) {}
            }
            return JobResult::failure($e->getMessage());
        } finally {
            try { $this->removeVolume($volumeName); } catch (\Throwable) {}
        }
    }

    // ---------------------------------------------------------------------------
    // Docker API calls
    // ---------------------------------------------------------------------------

    private function ensureVolume(string $name): void
    {
        $r = $this->request('POST', '/volumes/create', ['Name' => $name]);
        if ($r['status'] >= 500) {
            throw new \RuntimeException("Docker volume create failed ({$r['status']}): {$r['body']}");
        }
    }

    private function removeVolume(string $name): void
    {
        $this->request('DELETE', "/volumes/{$name}");
    }

    private function pullImage(string $image): void
    {
        [$from, $tag] = array_pad(explode(':', $image, 2), 2, 'latest');
        $r = $this->request('POST', "/images/create?fromImage={$from}&tag={$tag}");
        if ($r['status'] >= 400) {
            throw new \RuntimeException("Docker image pull failed ({$r['status']}): {$r['body']}");
        }
    }

    private function createContainer(Job $job, string $image, string $volumeName, Environment $environment): string
    {
        $r = $this->request('POST', '/containers/create', [
            'Image' => $image,
            'Cmd' => ['/bin/sh', '-c', $job->script ?? 'true'],
            'Env' => ["PIPELINE_ENV={$environment->value}"],
            'WorkingDir' => '/workspace',
            'HostConfig' => [
                'Binds' => ["{$volumeName}:/workspace"],
                'AutoRemove' => false,
            ],
        ]);

        if ($r['status'] >= 400) {
            throw new \RuntimeException("Docker container create failed ({$r['status']}): {$r['body']}");
        }

        $data = json_decode($r['body'], true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['Id'])) {
            throw new \RuntimeException("Docker container create returned no ID: {$r['body']}");
        }
        return $data['Id'];
    }

    private function startContainer(string $id): void
    {
        $r = $this->request('POST', "/containers/{$id}/start");
        if ($r['status'] >= 400) {
            throw new \RuntimeException("Docker container start failed ({$r['status']}): {$r['body']}");
        }
    }

    private function waitContainer(string $id): int
    {
        $r = $this->request('POST', "/containers/{$id}/wait");
        $data = json_decode($r['body'], true, 512, JSON_THROW_ON_ERROR);
        return $data['StatusCode'] ?? 1;
    }

    private function getLogs(string $id): string
    {
        $r = $this->request('GET', "/containers/{$id}/logs?stdout=1&stderr=1");
        return $this->stripDockerLogHeaders($r['body']);
    }

    private function removeContainer(string $id): void
    {
        $this->request('DELETE', "/containers/{$id}?force=1");
    }

    // ---------------------------------------------------------------------------
    // Raw HTTP over Unix socket
    // ---------------------------------------------------------------------------

    /**
     * @param array<string,mixed>|null $body
     * @return array{status:int,body:string}
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $socket = stream_socket_client(
            'unix://' . $this->socketPath,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            throw new \RuntimeException("Docker socket connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 300);

        $bodyJson = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '';
        $headers = implode("\r\n", [
            "{$method} {$path} HTTP/1.1",
            'Host: localhost',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($bodyJson),
            'Connection: close',
        ]);

        fwrite($socket, $headers . "\r\n\r\n" . $bodyJson);

        $raw = '';
        while (!feof($socket)) {
            $raw .= fread($socket, 65536);
        }
        fclose($socket);

        if (!str_contains($raw, "\r\n\r\n")) {
            throw new \RuntimeException("Malformed Docker API response for {$method} {$path}");
        }

        [$headerSection, $responseBody] = explode("\r\n\r\n", $raw, 2);

        if (stripos($headerSection, 'Transfer-Encoding: chunked') !== false) {
            $responseBody = $this->decodeChunked($responseBody);
        }

        preg_match('/HTTP\/\d\.\d (\d+)/', $headerSection, $m);
        $status = (int) ($m[1] ?? 500);

        return ['status' => $status, 'body' => $responseBody];
    }

    private function decodeChunked(string $data): string
    {
        $output = '';
        while (strlen($data) > 0) {
            $pos = strpos($data, "\r\n");
            if ($pos === false) break;
            $size = hexdec(substr($data, 0, $pos));
            if ($size === 0) break;
            $output .= substr($data, $pos + 2, $size);
            $data = substr($data, $pos + 2 + $size + 2);
        }
        return $output;
    }

    /**
     * Docker multiplexed log stream: 8-byte header per chunk.
     * Bytes 0: stream type. Bytes 4-7: payload size (big-endian uint32).
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
