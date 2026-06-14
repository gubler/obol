<?php

// ABOUTME: Repository for ObligationSnapshot rows (the native-per-currency obligation series).
// ABOUTME: findLatest backs the recorder's "append only when changed" guard; ULID ids order chronologically.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ObligationSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
     * The most recently recorded snapshot, or null when none exists. Ordered by the ULID id, which is
     * monotonic, so this is true insertion order even for several snapshots sharing a recorded-at date.
     */
    public function findLatest(): ?ObligationSnapshot
    {
        $snapshot = $this->createQueryBuilder('o')
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        \assert(null === $snapshot || $snapshot instanceof ObligationSnapshot);

        return $snapshot;
    }

    /**
     * The whole series, oldest first. The ULID id is monotonic, so ordering by it is chronological even for
     * several snapshots recorded on the same date. The obligation trend carries these forward by date.
     *
     * @return list<ObligationSnapshot>
     */
    public function findAllOrderedByRecordedAt(): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
