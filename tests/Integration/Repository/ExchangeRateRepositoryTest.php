<?php

// ABOUTME: Integration tests for ExchangeRateRepository::latestRate against a real database.
// ABOUTME: Covers latest-wins ordering, the as-of historical filter, and the missing-rate null case.

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use App\ValueObject\CalendarDate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExchangeRateRepositoryTest extends WebTestCase
{
    private function persistRate(EntityManagerInterface $entityManager, Currency $currency, float $rate, string $asOf): void
    {
        $entityManager->persist(new ExchangeRate($currency, $rate, CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable($asOf), new \DateTimeZone('UTC'))));
    }

    public function testReturnsTheMostRecentRateForACurrency(): void
    {
        self::createClient();
        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var ExchangeRateRepository $repository */
        $repository = $entityManager->getRepository(ExchangeRate::class);

        $this->persistRate($entityManager, Currency::USD, 1.05, '2024-01-01');
        $this->persistRate($entityManager, Currency::USD, 1.09, '2024-01-03');
        $this->persistRate($entityManager, Currency::USD, 1.07, '2024-01-02');
        $this->persistRate($entityManager, Currency::JPY, 162.0, '2024-01-03');
        $entityManager->flush();

        self::assertSame(1.09, $repository->latestRate(Currency::USD));
    }

    public function testReturnsTheLatestRateOnOrBeforeAnAsOfDate(): void
    {
        self::createClient();
        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var ExchangeRateRepository $repository */
        $repository = $entityManager->getRepository(ExchangeRate::class);

        $this->persistRate($entityManager, Currency::USD, 1.05, '2024-01-01');
        $this->persistRate($entityManager, Currency::USD, 1.09, '2024-01-03');
        $entityManager->flush();

        self::assertSame(1.05, $repository->latestRate(Currency::USD, CalendarDate::fromString('2024-01-02')));
    }

    public function testReturnsNullWhenNoRateIsStoredForTheCurrency(): void
    {
        self::createClient();
        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var ExchangeRateRepository $repository */
        $repository = $entityManager->getRepository(ExchangeRate::class);

        self::assertNull($repository->latestRate(Currency::GBP));
    }
}
