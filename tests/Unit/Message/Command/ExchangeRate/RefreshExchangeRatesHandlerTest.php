<?php

// ABOUTME: Unit tests for RefreshExchangeRatesHandler storing the day's EUR-pivot rates.
// ABOUTME: Verifies one row per supported currency and idempotency (skips rates already stored).

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\ExchangeRate;

use App\Entity\ExchangeRate;
use App\Enum\Currency;
use App\Message\Command\ExchangeRate\RefreshExchangeRatesCommand;
use App\Message\Command\ExchangeRate\RefreshExchangeRatesHandler;
use App\Repository\ExchangeRateRepository;
use App\Service\ExchangeRateProviderInterface;
use App\Service\RateSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RefreshExchangeRatesHandlerTest extends TestCase
{
    public function testStoresARatePerSupportedCurrencySkippingAnyAlreadyStoredForTheDay(): void
    {
        $date = new \DateTimeImmutable('2024-06-10');
        $snapshot = new RateSnapshot($date, ['EUR' => 1.0, 'USD' => 1.07, 'JPY' => 169.4]);

        $provider = $this->createMock(ExchangeRateProviderInterface::class);
        $provider->method('fetchLatest')->willReturn($snapshot);

        $repository = $this->createMock(ExchangeRateRepository::class);
        // USD is already stored for the day; EUR and JPY are new.
        $repository->method('hasRateFor')->willReturnCallback(
            static fn (Currency $currency, \DateTimeImmutable $asOf): bool => Currency::USD === $currency,
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::isInstanceOf(ExchangeRate::class))
        ;
        // The command bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        (new RefreshExchangeRatesHandler($provider, $repository, $entityManager))(new RefreshExchangeRatesCommand());
    }
}
