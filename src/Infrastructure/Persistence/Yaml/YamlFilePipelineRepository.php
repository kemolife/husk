<?php

namespace App\Infrastructure\Persistence\Yaml;

use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
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

        $data = Yaml::parseFile($file);

        $jobs = [];
        foreach ($data['jobs'] ?? [] as $jobId => $jobData) {
            $type = isset($jobData['type']) && $jobData['type'] === 'approval'
                ? JobType::APPROVAL
                : JobType::SCRIPT;

            $needs = isset($jobData['needs'])
                ? (array) $jobData['needs']
                : [];

            $jobs[] = new Job(
                id: $jobId,
                type: $type,
                image: $jobData['image'] ?? null,
                script: $jobData['script'] ?? null,
                needs: $needs,
                condition: $jobData['if'] ?? null,
            );
        }

        return new Pipeline(new PipelineId($id->value), $data['name'] ?? $id->value, $jobs);
    }
}
