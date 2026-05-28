<?php

namespace App\Domain\Pipeline;

class PipelineNotFoundException extends \DomainException
{
    public static function forId(string $id): self
    {
        return new self("Pipeline '{$id}' not found");
    }
}
