<?php

namespace App\Infrastructure\Executor;

use App\Application\Port\ExecutorPort;
use App\Application\Port\JobResult;
use App\Application\Port\SecretRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

class DockerSocketExecutorAdapter implements ExecutorPort
{
    public function __construct(
        private readonly string $socketPath = '/var/run/docker.sock',
        private readonly ?SecretRepositoryPort $secretRepo = null,
        private readonly string $logDir = '/tmp/husk-logs',
    ) {}

    public function run(Job $job, Environment $environment, string $runId): JobResult
    {
        $volumeName = "pipeline-run-{$runId}";
        $image = $job->image ?? 'alpine:latest';
        $containerId = null;

        try {
            $this->ensureLogDir();
            $this->ensureVolume($volumeName);
            $this->pullImage($image);
            $containerId = $this->createContainer($job, $image, $volumeName, $environment);
            $this->startContainer($containerId);

            $logFile = $this->logDir . '/' . $runId . '.log';
            $prefix = "Pulling image: {$image}\n---\n";
            file_put_contents($logFile, $prefix);

            // Poll Docker for logs every 500ms, write to shared file so SSE can tail it
            $exitCode = $this->pollContainerLogs($containerId, $logFile, $prefix, $job->timeoutSeconds);

            $output = (string) file_get_contents($logFile);
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

    private function ensureLogDir(): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

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
        $env = ["PIPELINE_ENV={$environment->value}"];

        foreach ($job->secretNames as $secretName) {
            $value = $this->secretRepo?->get($secretName);
            if ($value !== null) {
                $env[] = "{$secretName}={$value}";
            }
        }

        $r = $this->request('POST', '/containers/create', [
            'Image' => $image,
            'Cmd' => ['/bin/sh', '-c', $job->script ?? 'true'],
            'Env' => $env,
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

    /**
     * Poll container logs every 500ms, writing incrementally to the shared log file.
     * The SSE controller tails the same file so the user sees output as it arrives.
     */
    private function pollContainerLogs(string $id, string $logFile, string $prefix, ?int $timeoutSeconds): int
    {
        $deadline = time() + ($timeoutSeconds ?? 3600);

        while (time() < $deadline) {
            usleep(500_000); // 500ms

            // Write current logs to file
            $r = $this->request('GET', "/containers/{$id}/logs?stdout=1&stderr=1");
            if ($r['status'] < 300) {
                $output = $this->stripDockerLogHeaders($r['body']);
                file_put_contents($logFile, $prefix . $output);
            }

            // Check container state
            $r2 = $this->request('GET', "/containers/{$id}/json");
            if ($r2['status'] < 300) {
                $state = json_decode($r2['body'], true, 512, JSON_THROW_ON_ERROR)['State'] ?? [];
                if (!($state['Running'] ?? true)) {
                    // Final log write
                    $r3 = $this->request('GET', "/containers/{$id}/logs?stdout=1&stderr=1");
                    if ($r3['status'] < 300) {
                        file_put_contents($logFile, $prefix . $this->stripDockerLogHeaders($r3['body']));
                    }
                    return (int) ($state['ExitCode'] ?? 1);
                }
            }
        }

        // Timed out — kill container
        try { $this->request('POST', "/containers/{$id}/kill"); } catch (\Throwable) {}
        return 1;
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
