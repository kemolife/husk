<?php

namespace App\Tests\Domain\Pipeline;

use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function test_it_finds_job_by_id(): void
    {
        $job = new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null);
        $pipeline = new Pipeline(new PipelineId('my-pipeline'), 'My Pipeline', [$job]);

        $found = $pipeline->job('build');

        $this->assertSame($job, $found);
    }

    public function test_it_throws_when_job_not_found(): void
    {
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', []);

        $this->expectException(\InvalidArgumentException::class);
        $pipeline->job('nonexistent');
    }
}
