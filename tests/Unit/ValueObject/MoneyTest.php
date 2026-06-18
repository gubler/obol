<?php

// ABOUTME: Unit tests for the Money value object - minor-unit amount plus Currency.
// ABOUTME: Verifies construction, same-currency arithmetic, equality, and per-currency formatting.

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Enum\Currency;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testExposesItsMinorAmountAndCurrency(): void
    {
        $money = new Money(1599, Currency::USD);

        self::assertSame(1599, $money->minorAmount);
        self::assertSame(Currency::USD, $money->currency);
    }

    public function testAddsTwoAmountsOfTheSameCurrencyWithoutMutatingTheOriginals(): void
    {
        $a = new Money(1599, Currency::USD);
        $b = new Money(401, Currency::USD);

        $sum = $a->add($b);

        self::assertSame(2000, $sum->minorAmount);
        self::assertSame(Currency::USD, $sum->currency);
        self::assertSame(1599, $a->minorAmount);
    }

    public function testRefusesToAddAcrossCurrencies(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);
        (new Money(1000, Currency::USD))->add(new Money(1000, Currency::EUR));
    }

    public function testEqualityComparesAmountAndCurrency(): void
    {
        self::assertTrue((new Money(1599, Currency::USD))->equals(new Money(1599, Currency::USD)));
        self::assertFalse((new Money(1599, Currency::USD))->equals(new Money(1599, Currency::EUR)));
        self::assertFalse((new Money(1599, Currency::USD))->equals(new Money(1600, Currency::USD)));
    }

    public function testFormatsWithTheCurrencySymbolAndItsFractionDigits(): void
    {
        self::assertSame('$15.99', (new Money(1599, Currency::USD))->format('en'));
        self::assertSame('¥2,000', (new Money(2000, Currency::JPY))->format('en'));
        self::assertSame('€15.99', (new Money(1599, Currency::EUR))->format('en'));
    }

    public function testFormatsForTheGivenLocale(): void
    {
        $formatted = (new Money(159999, Currency::USD))->format('de_DE');

        // German swaps the grouping/decimal separators and places the symbol last.
        self::assertStringContainsString('1.599,99', $formatted);
        self::assertStringEndsWith('$', $formatted);
    }
}
