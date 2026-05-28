<?php

namespace App\Application\Port;

final readonly class JobResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $output,
    ) {}

    public static function success(string $output = ''): self
    {
        return new self(true, $output);
    }

    public static function failure(string $output = ''): self
    {
        return new self(false, $output);
    }

    public function isSuccess(): bool { return $this->success; }
}
