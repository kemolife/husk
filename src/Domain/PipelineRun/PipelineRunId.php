<?php

namespace App\Domain\PipelineRun;

use Symfony\Component\Uid\Uuid;

final readonly class PipelineRunId
{
    public function __construct(public readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('PipelineRunId cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
