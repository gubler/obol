<?php

// ABOUTME: Repository for ExchangeRate rows (daily EUR-pivot rates).
// ABOUTME: hasRateFor backs the puller's idempotency - one row per currency per day.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeRate>
 */
class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    public function hasRateFor(Currency $currency, \DateTimeImmutable $asOf): bool
    {
        return null !== $this->findOneBy(['currency' => $currency, 'asOf' => $asOf]);
    }

    /**
     * The most recent EUR-pivot rate for a currency (units of the currency per 1 EUR), or null when
     * none is stored. With `$asOf` set, the latest rate dated on or before it - the historical lookup
     * a future report can use; without it, the latest rate overall.
     */
    public function latestRate(Currency $currency, ?\DateTimeImmutable $asOf = null): ?float
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.currency = :currency')
            ->setParameter('currency', $currency)
            ->orderBy('r.asOf', 'DESC')
            ->setMaxResults(1)
        ;

        if ($asOf instanceof \DateTimeImmutable) {
            $qb->andWhere('r.asOf <= :asOf')->setParameter('asOf', $asOf);
        }

        $rate = $qb->getQuery()->getOneOrNullResult();
        \assert(null === $rate || $rate instanceof ExchangeRate);

        return $rate?->rate;
    }
}
