<?php

namespace App\Domain\Pipeline;

final readonly class Job
{
    /** @param string[] $needs */
    public function __construct(
        public readonly string $id,
        public readonly JobType $type,
        public readonly ?string $image,
        public readonly ?string $script,
        public readonly array $needs,
        public readonly ?string $condition,
    ) {}

    public function isApproval(): bool
    {
        return $this->type === JobType::APPROVAL;
    }
}
