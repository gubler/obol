<?php

// ABOUTME: Doctrine repository for the PaymentSource entity; owner-scoped lookups for per-user isolation.
// ABOUTME: findForOwner returns null cross-owner so a foreign id becomes a 404 (see ADR-0015).

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PaymentSource>
 */
class PaymentSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentSource::class);
    }

    /**
     * A single payment source owned by the given user, or null when it does not exist or belongs to
     * someone else. The null-cross-owner result is what turns a foreign id into a 404 (see ADR-0015).
     */
    public function findForOwner(Ulid $id, Ulid $ownerId): ?PaymentSource
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.owner = :owner')
            ->setParameter('id', $id, UlidType::NAME)
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof PaymentSource ? $result : null;
    }

    /**
     * A user's payment sources, ordered by name.
     *
     * @return list<PaymentSource>
     */
    public function findAllForOwner(Ulid $ownerId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
