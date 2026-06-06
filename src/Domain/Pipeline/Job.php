<?php

namespace App\Domain\Pipeline;

final readonly class Job
{
    /**
     * @param string[] $needs
     * @param string[] $secretNames
     */
    public function __construct(
        public readonly string $id,
        public readonly JobType $type,
        public readonly ?string $image,
        public readonly ?string $script,
        public readonly array $needs,
        public readonly ?string $condition,
        public readonly bool $continueOnError = false,
        public readonly ?int $timeoutSeconds = null,
        public readonly ?RetryPolicy $retry = null,
        public readonly array $secretNames = [],
        public readonly ?MatrixStrategy $matrix = null,
    ) {}

    public function isApproval(): bool
    {
        return $this->type === JobType::APPROVAL;
    }

    /** @param array<string, string> $values */
    public function withMatrixValues(array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $substitute = static function (?string $template, array $values): ?string {
            if ($template === null) {
                return null;
            }
            foreach ($values as $key => $value) {
                $template = str_replace('${{ matrix.' . $key . ' }}', $value, $template);
            }
            return $template;
        };

        return new self(
            id: $this->id,
            type: $this->type,
            image: $substitute($this->image, $values),
            script: $substitute($this->script, $values),
            needs: $this->needs,
            condition: $this->condition,
            continueOnError: $this->continueOnError,
            timeoutSeconds: $this->timeoutSeconds,
            retry: $this->retry,
            secretNames: $this->secretNames,
            matrix: $this->matrix,
        );
    }
}
