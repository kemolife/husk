<?php

namespace App\Infrastructure\Secrets;

use App\Application\Port\SecretRepositoryPort;

class EnvSecretAdapter implements SecretRepositoryPort
{
    public function get(string $name): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);
        return $value !== false ? (string) $value : null;
    }
}
