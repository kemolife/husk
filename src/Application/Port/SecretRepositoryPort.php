<?php

namespace App\Application\Port;

interface SecretRepositoryPort
{
    public function get(string $name): ?string;
}
