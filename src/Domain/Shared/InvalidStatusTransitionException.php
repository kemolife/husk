<?php

namespace App\Domain\Shared;

class InvalidStatusTransitionException extends \DomainException
{
    public static function for(string $entity, string $from, string $to): self
    {
        return new self("Invalid transition for {$entity}: {$from} → {$to}");
    }
}
