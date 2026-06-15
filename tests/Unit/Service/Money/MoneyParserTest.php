<?php

// ABOUTME: Unit tests for MoneyParser - locale-aware major<->minor money conversion.
// ABOUTME: Covers decimal/grouping separators, currency symbols, fraction-digit scaling, and rejection.

declare(strict_types=1);

namespace App\Tests\Unit\Service\Money;

use App\Service\Money\Exception\MoneyParseException;
use App\Service\Money\MoneyParser;
use PHPUnit\Framework\TestCase;

final class MoneyParserTest extends TestCase
{
    private MoneyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MoneyParser();
    }

    public function testParsesAPlainDecimalToMinorUnits(): void
    {
        self::assertSame(3550, $this->parser->toMinor('35.50', 2, 'en'));
    }

    public function testParsesAWholeNumberToMinorUnits(): void
    {
        self::assertSame(3500, $this->parser->toMinor('35', 2, 'en'));
    }

    public function testTreatsAGroupingSeparatorAsThousandsNotADecimal(): void
    {
        self::assertSame(100000, $this->parser->toMinor('1,000', 2, 'en'));
    }

    public function testParsesGroupedAmountWithDecimals(): void
    {
        self::assertSame(3430432, $this->parser->toMinor('34,304.32', 2, 'en'));
    }

    public function testStripsACurrencySymbolAndWhitespace(): void
    {
        self::assertSame(3430432, $this->parser->toMinor('$34,304.32', 2, 'en'));
        self::assertSame(1099, $this->parser->toMinor('  $10.99 ', 2, 'en'));
    }

    public function testHonoursLocaleSeparators(): void
    {
        // German: '.' groups thousands, ',' is the decimal point.
        self::assertSame(400034, $this->parser->toMinor('4.000,34', 2, 'de'));
    }

    public function testScalesByCurrencyFractionDigits(): void
    {
        // Zero-decimal currency (e.g. JPY): the amount is already in minor units.
        self::assertSame(2000, $this->parser->toMinor('2,000', 0, 'en'));
    }

    public function testRoundsToTheNearestMinorUnit(): void
    {
        self::assertSame(3600, $this->parser->toMinor('35.999', 2, 'en'));
    }

    public function testRejectsNonNumericInput(): void
    {
        $this->expectException(MoneyParseException::class);
        $this->parser->toMinor('not a number', 2, 'en');
    }

    public function testRejectsAnEmptyString(): void
    {
        $this->expectException(MoneyParseException::class);
        $this->parser->toMinor('   ', 2, 'en');
    }

    public function testFormatsMinorUnitsBackToAMajorStringForPrefill(): void
    {
        self::assertSame('35.50', $this->parser->toMajorString(3550, 2, 'en'));
        self::assertSame('2000', $this->parser->toMajorString(2000, 0, 'en'));
    }

    public function testMajorStringRoundTripsThroughToMinor(): void
    {
        $major = $this->parser->toMajorString(3430432, 2, 'en');

        self::assertSame(3430432, $this->parser->toMinor($major, 2, 'en'));
    }
}
