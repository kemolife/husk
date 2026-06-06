<?php

namespace App\Infrastructure\Http\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'health', methods: ['GET'])]
class HealthController
{
    public function __construct(private readonly Connection $connection) {}

    public function __invoke(): JsonResponse
    {
        $checks = ['db' => false, 'status' => 'degraded'];

        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['db'] = true;
            $checks['status'] = 'ok';
        } catch (\Throwable) {
        }

        $statusCode = $checks['status'] === 'ok'
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse($checks, $statusCode);
    }
}
