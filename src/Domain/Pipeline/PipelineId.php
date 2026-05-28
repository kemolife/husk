<?php

namespace App\Domain\Pipeline;

final readonly class PipelineId
{
    public function __construct(public readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('PipelineId cannot be empty');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
