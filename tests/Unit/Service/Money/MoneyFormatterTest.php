<?php

// ABOUTME: Unit tests for MoneyFormatter - renders a Money in the currency's convention for the ambient locale.
// ABOUTME: Locale comes from the injected LocaleSwitcher, not a global, so formatting is honest off-request too.

declare(strict_types=1);

namespace App\Tests\Unit\Service\Money;

use App\Enum\Currency;
use App\Service\Money\MoneyFormatter;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class MoneyFormatterTest extends TestCase
{
    public function testFormatsWithTheCurrencySymbolAndItsFractionDigits(): void
    {
        $formatter = new MoneyFormatter(new LocaleSwitcher('en', []));

        self::assertSame('$15.99', $formatter->format(new Money(1599, Currency::USD)));
        self::assertSame('¥2,000', $formatter->format(new Money(2000, Currency::JPY)));
        self::assertSame('€15.99', $formatter->format(new Money(1599, Currency::EUR)));
    }

    public function testFollowsTheAmbientLocale(): void
    {
        $formatter = new MoneyFormatter(new LocaleSwitcher('de_DE', []));

        // German swaps the grouping/decimal separators and places the symbol last.
        $formatted = $formatter->format(new Money(159999, Currency::USD));

        self::assertStringContainsString('1.599,99', $formatted);
        self::assertStringEndsWith('$', $formatted);
    }

    public function testAZeroDecimalCurrencyNeverGainsSpuriousDecimals(): void
    {
        $formatter = new MoneyFormatter(new LocaleSwitcher('en', []));

        self::assertSame('¥2,000', $formatter->format(new Money(2000, Currency::JPY)));
    }
}
