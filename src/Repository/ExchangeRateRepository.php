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
}
