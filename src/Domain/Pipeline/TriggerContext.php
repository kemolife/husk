<?php

namespace App\Domain\Pipeline;

final readonly class TriggerContext
{
    public function __construct(
        public ?string $branch = null,
        public ?string $commitSha = null,
        public ?string $actor = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'branch' => $this->branch,
            'commitSha' => $this->commitSha,
            'actor' => $this->actor,
        ], fn($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            branch: $data['branch'] ?? null,
            commitSha: $data['commitSha'] ?? null,
            actor: $data['actor'] ?? null,
        );
    }
}
