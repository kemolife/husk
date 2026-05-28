<?php

namespace App\Application\Port;

use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

interface ExecutorPort
{
    public function run(Job $job, Environment $environment): JobResult;
}
