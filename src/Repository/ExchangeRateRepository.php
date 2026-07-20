<?php

// ABOUTME: Repository for ExchangeRate rows (daily EUR-pivot rates).
// ABOUTME: hasRateFor backs the puller's idempotency - one row per currency per day.

declare(strict_types=1);

namespace App\Repository;

use App\Doctrine\Type\CalendarDateType;
use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\ValueObject\CalendarDate;
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

    public function hasRateFor(Currency $currency, CalendarDate $asOf): bool
    {
        return null !== $this->findOneBy(['currency' => $currency, 'asOf' => $asOf]);
    }

    /**
     * The most recent EUR-pivot rate for a currency (units of the currency per 1 EUR), or null when
     * none is stored. With `$asOf` set, the latest rate dated on or before that calendar day - the
     * historical lookup a report uses for the owner's local day; without it, the latest rate overall.
     */
    public function latestRate(Currency $currency, ?CalendarDate $asOf = null): ?float
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.currency = :currency')
            ->setParameter('currency', $currency)
            ->orderBy('r.asOf', 'DESC')
            ->setMaxResults(1)
        ;

        if ($asOf instanceof CalendarDate) {
            $qb->andWhere('r.asOf <= :asOf')->setParameter('asOf', $asOf, CalendarDateType::NAME);
        }

        $rate = $qb->getQuery()->getOneOrNullResult();
        \assert(null === $rate || $rate instanceof ExchangeRate);

        return $rate?->rate;
    }
}
