<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Auth\ApiKey;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineApiKeyRepository
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(ApiKey $key): void
    {
        $this->em->persist($key);
        $this->em->flush();
    }

    public function findByPlainKey(string $plainKey): ?ApiKey
    {
        return $this->em->createQueryBuilder()
            ->select('k')
            ->from(ApiKey::class, 'k')
            ->where('k.hashedKey = :hash')
            ->setParameter('hash', hash('sha256', $plainKey))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
