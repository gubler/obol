<?php

// ABOUTME: Integration tests for ExchangeRateRepository::latestRate against a real database.
// ABOUTME: Covers latest-wins ordering, the as-of historical filter, and the missing-rate null case.

declare(strict_types=1);

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Repository\ExchangeRateRepository;
use Doctrine\ORM\EntityManagerInterface;

function persistRate(EntityManagerInterface $entityManager, Currency $currency, float $rate, string $asOf): void
{
    $entityManager->persist(new ExchangeRate($currency, $rate, new DateTimeImmutable($asOf)));
}

test('returns the most recent rate for a currency', function (): void {
    $this->createClient();
    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    /** @var ExchangeRateRepository $repository */
    $repository = $entityManager->getRepository(ExchangeRate::class);

    persistRate($entityManager, Currency::USD, 1.05, '2024-01-01');
    persistRate($entityManager, Currency::USD, 1.09, '2024-01-03');
    persistRate($entityManager, Currency::USD, 1.07, '2024-01-02');
    persistRate($entityManager, Currency::JPY, 162.0, '2024-01-03');
    $entityManager->flush();

    expect($repository->latestRate(Currency::USD))->toBe(1.09);
});

test('returns the latest rate on or before an as-of date', function (): void {
    $this->createClient();
    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    /** @var ExchangeRateRepository $repository */
    $repository = $entityManager->getRepository(ExchangeRate::class);

    persistRate($entityManager, Currency::USD, 1.05, '2024-01-01');
    persistRate($entityManager, Currency::USD, 1.09, '2024-01-03');
    $entityManager->flush();

    expect($repository->latestRate(Currency::USD, new DateTimeImmutable('2024-01-02')))->toBe(1.05);
});

test('returns null when no rate is stored for the currency', function (): void {
    $this->createClient();
    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(EntityManagerInterface::class);
    /** @var ExchangeRateRepository $repository */
    $repository = $entityManager->getRepository(ExchangeRate::class);

    expect($repository->latestRate(Currency::GBP))->toBeNull();
});
