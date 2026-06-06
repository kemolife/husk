<?php

namespace App\Domain\Auth;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_keys')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $hashedKey;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $plainKey, string $name)
    {
        $this->id = $id;
        $this->hashedKey = hash('sha256', $plainKey);
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string { return $this->id; }
    public function name(): string { return $this->name; }
    public function hashedKey(): string { return $this->hashedKey; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    public function verifies(string $plainKey): bool
    {
        return hash_equals($this->hashedKey, hash('sha256', $plainKey));
    }
}
