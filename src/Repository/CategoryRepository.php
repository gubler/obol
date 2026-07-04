<?php

// ABOUTME: Doctrine repository for the Category entity; owner-scoped lookups for per-user isolation.
// ABOUTME: findForOwner returns null cross-owner so a foreign id becomes a 404 (see ADR-0015).

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * A single category owned by the given user, or null when it does not exist or belongs to someone
     * else. The null-cross-owner result is what turns a foreign id into a 404 (see ADR-0015).
     */
    public function findForOwner(Ulid $id, Ulid $ownerId): ?Category
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.owner = :owner')
            ->setParameter('id', $id, UlidType::NAME)
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof Category ? $result : null;
    }

    /**
     * A user's categories, ordered by name.
     *
     * @return list<Category>
     */
    public function findAllForOwner(Ulid $ownerId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
