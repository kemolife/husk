<?php

namespace App\Infrastructure\Http\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ListPipelinesController
{
    public function __construct(private readonly string $pipelinesDir) {}

    #[Route('/pipelines', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $files = glob($this->pipelinesDir . '/*.yaml') ?: [];

        $pipelines = array_map(function (string $file): array {
            $id = basename($file, '.yaml');
            $data = \Symfony\Component\Yaml\Yaml::parseFile($file);
            return [
                'id' => $id,
                'name' => $data['name'] ?? $id,
            ];
        }, $files);

        return new JsonResponse(array_values($pipelines));
    }
}
