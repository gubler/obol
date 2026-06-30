<?php

// ABOUTME: Unit tests for the currency Converter - identity, direct, cross-pivot, and missing-rate paths.
// ABOUTME: Mocks the rate repository; rates are EUR-pivot (units of the currency per 1 EUR).

declare(strict_types=1);

namespace App\Tests\Unit\Message\Currency;

use App\Enum\Currency;
use App\Message\Currency\Converter;
use App\Repository\ExchangeRateRepository;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase
{
    public function testReturnsTheSameMoneyUnchangedWhenConvertingToItsOwnCurrency(): void
    {
        $repository = $this->createMock(ExchangeRateRepository::class);
        $repository->expects(self::never())->method('latestRate');

        $converted = new Converter($repository)->convert(new Money(500, Currency::USD), Currency::USD);

        self::assertTrue($converted->equals(new Money(500, Currency::USD)));
    }

    public function testConvertsDirectlyBetweenTwoCurrenciesViaTheEurPivot(): void
    {
        // 1 EUR = 1.08 USD; $10.80 is 10.00 EUR -> 1000 minor units.
        $repository = self::createStub(ExchangeRateRepository::class);
        $repository->method('latestRate')->willReturnMap([
            [Currency::USD, null, 1.08],
            [Currency::EUR, null, 1.0],
        ]);

        $converted = new Converter($repository)->convert(new Money(1080, Currency::USD), Currency::EUR);

        self::assertTrue($converted->equals(new Money(1000, Currency::EUR)));
    }

    public function testCrossConvertsAPairNeitherOfWhichIsEur(): void
    {
        // 1 EUR = 1.08 USD = 162.0 JPY. $10.80 -> 10.00 EUR -> 1620 yen (JPY has no minor units).
        $repository = self::createStub(ExchangeRateRepository::class);
        $repository->method('latestRate')->willReturnMap([
            [Currency::USD, null, 1.08],
            [Currency::JPY, null, 162.0],
        ]);

        $converted = new Converter($repository)->convert(new Money(1080, Currency::USD), Currency::JPY);

        self::assertTrue($converted->equals(new Money(1620, Currency::JPY)));
    }

    public function testPassesAnAsOfDateThroughToTheRateLookup(): void
    {
        $asOf = new \DateTimeImmutable('2024-01-01');
        $repository = self::createStub(ExchangeRateRepository::class);
        $repository->method('latestRate')->willReturnMap([
            [Currency::USD, $asOf, 1.08],
            [Currency::EUR, $asOf, 1.0],
        ]);

        $converted = new Converter($repository)->convert(new Money(1080, Currency::USD), Currency::EUR, $asOf);

        self::assertTrue($converted->equals(new Money(1000, Currency::EUR)));
    }

    public function testThrowsWhenARateIsMissingForEitherCurrency(): void
    {
        $repository = self::createStub(ExchangeRateRepository::class);
        $repository->method('latestRate')->willReturn(null);

        $this->expectException(\Assert\InvalidArgumentException::class);

        new Converter($repository)->convert(new Money(1080, Currency::USD), Currency::JPY);
    }
}
