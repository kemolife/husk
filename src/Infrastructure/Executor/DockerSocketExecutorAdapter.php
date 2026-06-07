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

            // Stream logs to file while container runs; returns exit code when done
            $logFile = $this->logDir . '/' . $runId . '.log';
            file_put_contents($logFile, "Pulling image: {$image}\n---\n");
            $exitCode = $this->streamContainerLogs($containerId, $logFile, $job->timeoutSeconds);

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
     * Stream container logs to file in real time using Docker's /logs?follow=1 endpoint.
     * Uses stream_select() to multiplex the wait-for-exit socket and the log-stream socket.
     * Returns the container's exit code.
     */
    private function streamContainerLogs(string $id, string $logFile, ?int $timeoutSeconds): int
    {
        $deadline = time() + ($timeoutSeconds ?? 3600);

        // Socket A: wait for container exit (blocks until container stops)
        $waitSock = $this->openSocket();
        stream_set_blocking($waitSock, false);
        fwrite($waitSock, implode("\r\n", [
            "POST /containers/{$id}/wait HTTP/1.0",
            'Host: localhost',
            'Content-Length: 0',
        ]) . "\r\n\r\n");

        // Socket B: stream logs live (HTTP/1.0 avoids chunked encoding)
        $logSock = $this->openSocket();
        stream_set_blocking($logSock, false);
        fwrite($logSock, implode("\r\n", [
            "GET /containers/{$id}/logs?stdout=1&stderr=1&follow=1 HTTP/1.0",
            'Host: localhost',
        ]) . "\r\n\r\n");

        $fp = fopen($logFile, 'a');

        $waitBuf = '';
        $logBuf = '';
        $logHeaderDone = false;
        $exitCode = 1;

        while (time() < $deadline) {
            $read = [$waitSock, $logSock];
            $write = null;
            $except = null;

            $ready = stream_select($read, $write, $except, 1);
            if ($ready === false) {
                break;
            }

            foreach ($read as $s) {
                if ($s === $waitSock) {
                    $chunk = fread($waitSock, 65536);
                    if ($chunk !== false && $chunk !== '') {
                        $waitBuf .= $chunk;
                    }
                    if (feof($waitSock)) {
                        // Parse exit code from wait response
                        if (str_contains($waitBuf, "\r\n\r\n")) {
                            [, $body] = explode("\r\n\r\n", $waitBuf, 2);
                            try {
                                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                                $exitCode = $data['StatusCode'] ?? 1;
                            } catch (\Throwable) {}
                        }
                        // Drain remaining log data then stop
                        $this->drainLogSocket($logSock, $logBuf, $logHeaderDone, $fp);
                        goto done;
                    }
                } elseif ($s === $logSock) {
                    $chunk = fread($logSock, 8192);
                    if ($chunk !== false && $chunk !== '') {
                        $logBuf .= $chunk;
                    }
                    $this->processLogBuffer($logBuf, $logHeaderDone, $fp);
                }
            }
        }

        if (time() >= $deadline) {
            try { $this->request('POST', "/containers/{$id}/kill"); } catch (\Throwable) {}
        }

        done:
        fclose($fp);
        fclose($waitSock);
        fclose($logSock);

        return $exitCode;
    }

    /**
     * Process buffered log data: consume HTTP headers once, then strip Docker
     * 8-byte multiplexed log headers and write text to the log file.
     */
    private function processLogBuffer(string &$buf, bool &$headerDone, mixed $fp): void
    {
        if (!$headerDone) {
            $pos = strpos($buf, "\r\n\r\n");
            if ($pos === false) {
                return;
            }
            $buf = substr($buf, $pos + 4);
            $headerDone = true;
        }

        // Parse Docker multiplexed log format: 8-byte header per frame
        while (strlen($buf) >= 8) {
            $size = unpack('N', substr($buf, 4, 4))[1];
            if (strlen($buf) < 8 + $size) {
                break;
            }
            $text = substr($buf, 8, $size);
            $buf = substr($buf, 8 + $size);
            if ($text !== '') {
                fwrite($fp, $text);
                fflush($fp);
            }
        }
    }

    private function drainLogSocket(mixed $sock, string &$buf, bool &$headerDone, mixed $fp): void
    {
        stream_set_blocking($sock, true);
        stream_set_timeout($sock, 2);
        while (!feof($sock)) {
            $chunk = fread($sock, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buf .= $chunk;
            $this->processLogBuffer($buf, $headerDone, $fp);
        }
    }

    private function openSocket(): mixed
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
        return $socket;
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
        $socket = $this->openSocket();
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
}
