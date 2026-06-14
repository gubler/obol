<?php

// ABOUTME: Unit tests for the Currency enum - the curated ECB/Frankfurter-quoted set.
// ABOUTME: Verifies ISO-code backing, the supported set, fraction digits, symbol, and label.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testIsAStringBackedEnumOfIso4217Codes(): void
    {
        self::assertSame('USD', Currency::USD->value);
        self::assertSame(Currency::JPY, Currency::from('JPY'));
    }

    public function testCoversTheFrankfurterEcbQuotedCurrencySetAndNothingMore(): void
    {
        self::assertCount(30, Currency::cases());
        self::assertNotNull(Currency::tryFrom('USD'));
        self::assertNotNull(Currency::tryFrom('EUR'));
        self::assertNotNull(Currency::tryFrom('JPY'));
        // Not all of ISO 4217: a currency we cannot get a rate for is intentionally absent.
        self::assertNull(Currency::tryFrom('XAU'));
        self::assertNull(Currency::tryFrom('KWD'));
    }

    public function testReportsFractionDigitsPerCurrency(): void
    {
        self::assertSame(2, Currency::USD->fractionDigits());
        self::assertSame(2, Currency::EUR->fractionDigits());
        self::assertSame(0, Currency::JPY->fractionDigits());
    }

    public function testExposesASymbolAndAHumanLabel(): void
    {
        self::assertSame('$', Currency::USD->symbol());
        self::assertSame('¥', Currency::JPY->symbol());
        self::assertSame('£', Currency::GBP->symbol());
        self::assertStringContainsString('Dollar', Currency::USD->label());
    }
}
