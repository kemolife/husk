<?php

namespace App\Tests\Domain\Shared;

use App\Domain\Shared\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_it_creates_from_string(): void
    {
        $env = Environment::fromString('staging');
        $this->assertSame(Environment::STAGING, $env);
    }

    public function test_it_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        Environment::fromString('invalid');
    }
}
