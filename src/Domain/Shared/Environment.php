<?php

namespace App\Domain\Shared;

enum Environment: string
{
    case DEVELOPMENT = 'development';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
