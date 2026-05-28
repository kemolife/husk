<?php

namespace App\Domain\PipelineRun;

class PipelineRunNotFoundException extends \DomainException
{
    public static function forId(string $id): self
    {
        return new self("PipelineRun '{$id}' not found");
    }
}
