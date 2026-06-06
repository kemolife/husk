<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Port\ExecutorPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Shared\Environment;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class PlaygroundController
{
    private const MAX_TIMEOUT = 120;

    public function __construct(private readonly ExecutorPort $executor) {}

    #[Route('/playground/run', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $image = trim((string) ($body['image'] ?? ''));
        $script = trim((string) ($body['script'] ?? ''));
        $timeout = min((int) ($body['timeout'] ?? 30), self::MAX_TIMEOUT);

        if ($image === '') {
            throw new BadRequestHttpException('image is required');
        }
        if ($script === '') {
            throw new BadRequestHttpException('script is required');
        }

        $job = new Job(
            id: 'playground',
            type: JobType::SCRIPT,
            image: $image,
            script: $script,
            needs: [],
            condition: null,
            timeoutSeconds: $timeout,
        );

        $startedAt = new \DateTimeImmutable();
        $result = $this->executor->run($job, Environment::STAGING, Uuid::v4()->toRfc4122());
        $durationMs = (int) ((new \DateTimeImmutable())->getTimestamp() - $startedAt->getTimestamp()) * 1000;

        return new JsonResponse([
            'success' => $result->isSuccess(),
            'output' => $result->output,
            'durationMs' => $durationMs,
        ], $result->isSuccess() ? Response::HTTP_OK : Response::HTTP_OK);
    }
}
