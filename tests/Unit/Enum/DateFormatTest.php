<?php

// ABOUTME: Unit tests for the DateFormat enum - the ICU pattern per case and its translation key.
// ABOUTME: LocaleDefault has no pattern (defers to the locale's medium length); the rest are explicit.

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\DateFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateFormatTest extends TestCase
{
    #[DataProvider('provideMapsEachCaseToItsIcuPatternCases')]
    public function testMapsEachCaseToItsIcuPattern(DateFormat $format, ?string $expected): void
    {
        self::assertSame($expected, $format->pattern());
    }

    /**
     * @return iterable<string, array{DateFormat, ?string}>
     */
    public static function provideMapsEachCaseToItsIcuPatternCases(): iterable
    {
        yield 'locale default has no pattern' => [DateFormat::LocaleDefault, null];
        // The case value is the ICU pattern for the explicit formats.
        yield 'year-month-day dash' => [DateFormat::YearMonthDayDash, 'yyyy-MM-dd'];
        yield 'month/day/year slash' => [DateFormat::MonthDayYearSlash, 'MM/dd/yyyy'];
        yield 'day/month/year slash' => [DateFormat::DayMonthYearSlash, 'dd/MM/yyyy'];
    }

    public function testReturnsATranslationKeyPerCase(): void
    {
        self::assertSame('enum.date_format.locale_default', DateFormat::LocaleDefault->label());
        self::assertSame('enum.date_format.year_month_day_dash', DateFormat::YearMonthDayDash->label());
        self::assertSame('enum.date_format.month_day_year_slash', DateFormat::MonthDayYearSlash->label());
        self::assertSame('enum.date_format.day_month_year_slash', DateFormat::DayMonthYearSlash->label());
    }
}
