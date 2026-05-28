<?php

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
use App\Infrastructure\Persistence\Yaml\YamlFilePipelineRepository;
use PHPUnit\Framework\TestCase;

class YamlFilePipelineRepositoryTest extends TestCase
{
    private string $fixturesDir;
    private YamlFilePipelineRepository $repo;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
        mkdir($this->fixturesDir, 0777, true);
        $this->repo = new YamlFilePipelineRepository($this->fixturesDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->fixturesDir . '/*.yaml'));
        rmdir($this->fixturesDir);
    }

    public function test_it_loads_pipeline_from_yaml(): void
    {
        file_put_contents($this->fixturesDir . '/my-pipeline.yaml', <<<YAML
name: My Pipeline
jobs:
  build:
    image: php:8.4-cli
    script: composer install
  test:
    needs: build
    image: php:8.4-cli
    script: php bin/phpunit
YAML);

        $pipeline = $this->repo->findById(new PipelineId('my-pipeline'));

        $this->assertSame('My Pipeline', $pipeline->name());
        $this->assertCount(2, $pipeline->jobs());
        $this->assertSame('build', $pipeline->jobs()[0]->id);
        $this->assertSame(['build'], $pipeline->jobs()[1]->needs);
    }

    public function test_it_parses_approval_job_type(): void
    {
        file_put_contents($this->fixturesDir . '/approve-pipeline.yaml', <<<YAML
name: Approve Pipeline
jobs:
  gate:
    type: approval
YAML);

        $pipeline = $this->repo->findById(new PipelineId('approve-pipeline'));

        $this->assertSame(JobType::APPROVAL, $pipeline->jobs()[0]->type);
    }

    public function test_it_throws_when_pipeline_not_found(): void
    {
        $this->expectException(PipelineNotFoundException::class);
        $this->repo->findById(new PipelineId('nonexistent'));
    }
}
