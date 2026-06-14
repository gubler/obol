<?php

// ABOUTME: Unit tests for CurrencyTotaller - sums a mixed-currency list into a converted display total.
// ABOUTME: Returns a ConvertedTotal: the converted headline, the native per-currency breakdown, approximate flag.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Currency;

use App\Enum\Currency;
use App\Message\Currency\ConvertedTotal;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\ExchangeRateRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CurrencyTotallerTest extends TestCase
{
    /**
     * @param list<Money>          $amounts
     * @param array<string, float> $rates   EUR-pivot rates by currency code (units per 1 EUR)
     */
    private function totalAmounts(array $amounts, array $rates, string $displayCurrency = 'USD'): ConvertedTotal
    {
        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;

        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider($displayCurrency));

        return $totaller->total($amounts);
    }

    public function testTotalsAMixedCurrencyListConvertsToTheDisplayCurrencyAndKeepsAKeySortedBreakdown(): void
    {
        $total = $this->totalAmounts(
            amounts: [
                new Money(4000, Currency::USD),
                new Money(1000, Currency::USD),
                new Money(3000, Currency::EUR), // -> 3240 USD
            ],
            rates: ['EUR' => 1.0, 'USD' => 1.08],
        );

        self::assertInstanceOf(ConvertedTotal::class, $total);
        self::assertSame(8240, $total->converted->minorAmount);   // 5000 USD + 3240 USD
        self::assertSame(Currency::USD, $total->converted->currency);
        self::assertTrue($total->isApproximate);

        self::assertCount(2, $total->breakdown);
        // Key-sorted by currency code: EUR before USD; same-currency amounts are merged.
        self::assertSame(Currency::EUR, $total->breakdown[0]->currency);
        self::assertSame(3000, $total->breakdown[0]->minorAmount);
        self::assertSame(Currency::USD, $total->breakdown[1]->currency);
        self::assertSame(5000, $total->breakdown[1]->minorAmount);
    }

    public function testIsNotApproximateAndNeedsNoRateForASingleDisplayCurrencyList(): void
    {
        $total = $this->totalAmounts(amounts: [new Money(5800, Currency::USD)], rates: []);

        self::assertSame(5800, $total->converted->minorAmount);
        self::assertFalse($total->isApproximate);
        self::assertCount(1, $total->breakdown);
    }

    public function testReturnsAZeroDisplayCurrencyTotalForAnEmptyList(): void
    {
        $total = $this->totalAmounts(amounts: [], rates: []);

        self::assertSame(0, $total->converted->minorAmount);
        self::assertSame(Currency::USD, $total->converted->currency);
        self::assertFalse($total->isApproximate);
        self::assertSame([], $total->breakdown);
    }
}
