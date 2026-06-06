<?php

namespace App\Domain\Pipeline;

final readonly class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts,
        public readonly int $delaySeconds = 0,
    ) {}
}
