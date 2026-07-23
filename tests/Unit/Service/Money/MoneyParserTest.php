<?php

// ABOUTME: Unit tests for MoneyParser - locale-aware major<->minor money conversion.
// ABOUTME: Covers decimal/grouping separators, currency symbols, fraction-digit scaling, and rejection.

declare(strict_types=1);

namespace App\Tests\Unit\Service\Money;

use App\Service\Money\Exception\MoneyParseException;
use App\Service\Money\MoneyParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class MoneyParserTest extends TestCase
{
    private MoneyParser $parser;

    protected function setUp(): void
    {
        $this->parser = self::parserFor('en');
    }

    private static function parserFor(string $locale): MoneyParser
    {
        // Locale comes from the ambient LocaleSwitcher, exactly as it does in a request.
        return new MoneyParser(new LocaleSwitcher($locale, []));
    }

    public function testParsesAPlainDecimalToMinorUnits(): void
    {
        self::assertSame(3550, $this->parser->toMinor('35.50', 2));
    }

    public function testParsesAWholeNumberToMinorUnits(): void
    {
        self::assertSame(3500, $this->parser->toMinor('35', 2));
    }

    public function testTreatsAGroupingSeparatorAsThousandsNotADecimal(): void
    {
        self::assertSame(100000, $this->parser->toMinor('1,000', 2));
    }

    public function testParsesGroupedAmountWithDecimals(): void
    {
        self::assertSame(3430432, $this->parser->toMinor('34,304.32', 2));
    }

    public function testStripsACurrencySymbolAndWhitespace(): void
    {
        self::assertSame(3430432, $this->parser->toMinor('$34,304.32', 2));
        self::assertSame(1099, $this->parser->toMinor('  $10.99 ', 2));
    }

    public function testHonoursLocaleSeparators(): void
    {
        // German: '.' groups thousands, ',' is the decimal point.
        self::assertSame(400034, self::parserFor('de')->toMinor('4.000,34', 2));
    }

    public function testScalesByCurrencyFractionDigits(): void
    {
        // Zero-decimal currency (e.g. JPY): the amount is already in minor units.
        self::assertSame(2000, $this->parser->toMinor('2,000', 0));
    }

    public function testRoundsToTheNearestMinorUnit(): void
    {
        self::assertSame(3600, $this->parser->toMinor('35.999', 2));
    }

    public function testRejectsNonNumericInput(): void
    {
        $this->expectException(MoneyParseException::class);
        $this->parser->toMinor('not a number', 2);
    }

    public function testRejectsAnEmptyString(): void
    {
        $this->expectException(MoneyParseException::class);
        $this->parser->toMinor('   ', 2);
    }

    public function testFormatsMinorUnitsBackToAMajorStringForPrefill(): void
    {
        self::assertSame('35.50', $this->parser->toMajorString(3550, 2));
        self::assertSame('2000', $this->parser->toMajorString(2000, 0));
    }

    public function testMajorStringRoundTripsThroughToMinor(): void
    {
        $major = $this->parser->toMajorString(3430432, 2);

        self::assertSame(3430432, $this->parser->toMinor($major, 2));
    }
}
