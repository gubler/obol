<?php

// ABOUTME: Repository for ObligationSnapshot rows (each user's native-per-currency obligation series).
// ABOUTME: findLatestForOwner backs the recorder's "append only when changed" guard; ULID ids order chronologically.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ObligationSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<ObligationSnapshot>
 */
class ObligationSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObligationSnapshot::class);
    }

    /**
     * The given user's most recently recorded snapshot, or null when they have none. Ordered by the ULID
     * id, which is monotonic, so this is true insertion order even for several snapshots sharing a date.
     */
    public function findLatestForOwner(Ulid $ownerId): ?ObligationSnapshot
    {
        $snapshot = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        \assert(null === $snapshot || $snapshot instanceof ObligationSnapshot);

        return $snapshot;
    }

    /**
     * The given user's whole series, oldest first. The ULID id is monotonic, so ordering by it is
     * chronological even for several snapshots recorded on the same date. The trend carries these forward.
     *
     * @return list<ObligationSnapshot>
     */
    public function findAllOrderedByRecordedAtForOwner(Ulid $ownerId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
